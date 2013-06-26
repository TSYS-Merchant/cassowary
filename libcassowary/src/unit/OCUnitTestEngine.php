<?php

/*
Copyright 2013 iMobile3, LLC. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that adherence to the following
conditions is maintained. If you do not agree with these terms,
please do not use, install, modify or redistribute this software.

1. Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY IMOBILE3, LLC "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL IMOBILE3, LLC OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
* OCUnit wrapper
*
* To use, set unit_engine in .arcconfig, or use --engine flag
* with arc unit.
*
* @group unitrun
*/
final class OCUnitTestEngine extends ArcanistBaseUnitTestEngine {

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $resultArray = array();
        $testPaths = array();

        /* Looking for project root directory */
        foreach ($this->getPaths() as $path) {
            $rootPath = $this->projectRoot."/".$path;

            /* Checking all levels of path */
            do {
                /* Project root should have .xctool-args */
                /* Only add path once per project */
                if (file_exists($rootPath."/.xctool-args")
                && !in_array($rootPath, $testPaths)) {
                    array_push($testPaths, $rootPath);
                }

                /* Stripping last level */
                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }

        /* Checking to see if no paths were added */
        if (count($testPaths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        /* Trying to build for every project */
        foreach ($testPaths as $path) {
            chdir($path);

            exec(phutil_get_library_root("libcassowary").
              "/../../externals/xctool/xctool.sh -reporter phabricator:/tmp/results.phab test");
            $xctoolTestResults = json_decode(file_get_contents("/tmp/results.phab"), true);
            unlink("/tmp/results.phab");

            $testResult = $this->parseOutput($xctoolTestResults);

            $resultArray = array_merge($resultArray, $testResult);
        }

        return $resultArray;
    }

    private function parseOutput($testResults) {
        $result = null;
        $resultArray = array();

        /* Get build output directory, run gcov, and parse coverage results for all implementations */
        exec("xcodebuild -showBuildSettings | grep PROJECT_TEMP_DIR -m1 | grep -o '/.\+$'", $buildDirOutput, $_);
        $buildDirOutput[0] .= "/Debug-iphonesimulator/UnitTests.build/Objects-normal/i386/";
        chdir($buildDirOutput[0]);
        exec("gcov * > /dev/null 2> /dev/null");

        $coverage = array();
        foreach (glob("*.m.gcov") as $gcovFilename) {
            $str = '';

            foreach (file($gcovFilename) as $gcovLine) {
                if ($g = preg_match_all("/.*?(.):.*?(\\d+)/is", $gcovLine, $gcovMatches) && $gcovMatches[2][0] > 0) {
                    if ($gcovMatches[1][0] === '#' || $gcovMatches[1][0] === '=') {
                        $str .= 'U';
                    } else if ($gcovMatches[1][0] === '-') {
                        $str .= 'N';
                    } else if ($gcovMatches[1][0] > 0) {
                        $str .= 'C';
                    } else {
                        $str .= 'N';
                    }
                }
            }

            foreach ($this->getPaths() as $path) {
                if (strpos($path, str_replace(".gcov", "", $gcovFilename)) !== false) {
                    $coverage[$path] = $str;
                }
            }
        }

        /* Iterate through test results and locate passes / failures */
        foreach ($testResults as $key => $testResultItem) {
          $result = new ArcanistUnitTestResult();
          $result->setResult($testResultItem['result']);
          $result->setName($testResultItem['name']);
          $result->setUserData($testResultItem['userdata']);
          $result->setExtraData($testResultItem['extra']);
          $result->setLink($testResultItem['link']);
          $result->setCoverage($coverage);
          array_push($resultArray, $result);
        }

        return $resultArray;
    }
}
