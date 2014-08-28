<?php

/*
Copyright 2012-2014 iMobile3, LLC. All rights reserved.

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
final class OCUnitTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $result_array = array();
        $test_paths = array();

        /* Looking for project root directory */
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot."/".$path;

            /* Checking all levels of path */
            do {
                /* Project root should have .xctool-args */
                /* Only add path once per project */
                if (file_exists($root_path."/.xctool-args")
                && !in_array($root_path, $test_paths)) {
                    array_push($test_paths, $root_path);
                }

                /* Stripping last level */
                $last = strrchr($root_path, "/");
                $root_path = substr_replace($root_path, "", strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        /* Checking to see if no paths were added */
        if (count($test_paths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        /* Trying to build for every project */
        foreach ($test_paths as $path) {
            chdir($path);

            $result_location = tempnam(sys_get_temp_dir(), 'arctestresults.phab');
            exec(phutil_get_library_root("libcassowary").
              "/../../externals/xctool/xctool.sh -reporter phabricator:".$result_location." test");
            $xctool_test_results = json_decode(file_get_contents($result_location), true);
            unlink($result_location);

            $test_result = $this->parseOutput($xctool_test_results);

            $result_array = array_merge($result_array, $test_result);
        }

        return $result_array;
    }

    private function parseOutput($test_results) {
        $result = null;
        $result_array = array();

        /* Get build output directory, run gcov, and parse coverage results for all implementations */
        $build_dir_output = array();
        $_ = 0;
        exec("xcodebuild -showBuildSettings | grep PROJECT_TEMP_DIR -m1 | grep -o '/.\+$'", $build_dir_output, $_);
        $build_dir_output[0] .= "/Debug-iphonesimulator/UnitTests.build/Objects-normal/i386/";
        chdir($build_dir_output[0]);
        exec("gcov * > /dev/null 2> /dev/null");

        $coverage = array();
        foreach (glob("*.m.gcov") as $gcov_filename) {
            $str = '';

            foreach (file($gcov_filename) as $gcov_line) {
                $gcov_matches = array();
                if ($g = preg_match_all("/.*?(.):.*?(\\d+)/is", $gcov_line, $gcov_matches) && $gcov_matches[2][0] > 0) {
                    if ($gcov_matches[1][0] === '#' || $gcov_matches[1][0] === '=') {
                        $str .= 'U';
                    } else if ($gcov_matches[1][0] === '-') {
                        $str .= 'N';
                    } else if ($gcov_matches[1][0] > 0) {
                        $str .= 'C';
                    } else {
                        $str .= 'N';
                    }
                }
            }

            foreach ($this->getPaths() as $path) {
                if (strpos($path, str_replace(".gcov", "", $gcov_filename)) !== false) {
                    $coverage[$path] = $str;
                }
            }
        }

        /* Iterate through test results and locate passes / failures */
        foreach ($test_results as $key => $test_result_item) {
          $result = new ArcanistUnitTestResult();
          $result->setResult($test_result_item['result']);
          $result->setName($test_result_item['name']);
          $result->setUserData($test_result_item['userdata']);
          $result->setExtraData($test_result_item['extra']);
          $result->setLink($test_result_item['link']);
          $result->setCoverage($coverage);
          array_push($result_array, $result);
        }

        return $result_array;
    }
}
