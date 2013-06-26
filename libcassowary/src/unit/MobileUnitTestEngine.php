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
* Comprehensive linter that takes the various platform-specific linters
* (OCUnit, Android) and combines them into one unified use case.
*
* To use, set unit_engine in .arcconfig, or use --engine flag
* with arc unit.
*
* @group unitrun
*/
final class MobileUnitTestEngine extends ArcanistBaseUnitTestEngine {
    private $projectRoot;

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $resultArray = array();
        $iOSTestPaths = array();
        $androidTestPaths = array();

        /* Looking for project root directory */
        foreach ($this->getPaths() as $path) {
            $rootPath = $this->projectRoot."/".$path;

            /* Checking all levels of path */
            do {
                /* Project root should have .xctool-args */
                /* Only add path once per project */
                if (file_exists($rootPath."/.xctool-args")
                && !in_array($rootPath, $iOSTestPaths)) {
                    array_push($iOSTestPaths, $rootPath);
                }

                /* Stripping last level */
                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }

        foreach ($this->getPaths() as $path) {
            $rootPath = $this->projectRoot."/".$path;

            /* Checking all levels of path */
            do {
                /* Project root should have AndroidManifest.xml */
                /* We only want projects that have tests */
                /* Only add path once per project */
                if (file_exists($rootPath."/AndroidManifest.xml")
                && file_exists($rootPath."/tests")
                && !in_array($rootPath, $androidTestPaths)) {
                    array_push($androidTestPaths, $rootPath);
                }

                /* Stripping last level */
                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }

        /* Checking to see if no paths were added */
        if (count($iOSTestPaths) == 0 && count($androidTestPaths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        /* Trying to build for every project */
        foreach ($iOSTestPaths as $path) {
            chdir($path);

            exec(phutil_get_library_root("libcassowary").
              "/../../externals/xctool/xctool.sh -reporter phabricator:/tmp/results.phab test");
            $xctoolTestResults = json_decode(file_get_contents("/tmp/results.phab"), true);
            unlink("/tmp/results.phab");

            $testResult = $this->parseiOSOutput($xctoolTestResults);

            $resultArray = array_merge($resultArray, $testResult);
        }

        foreach ($androidTestPaths as $path) {
            /* Building Main Package */
            chdir($path);
            exec("ant clean");
            exec("android update project --path .");
            exec("ant debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RunTimeException("Unable to build using [ant debug]");
            }

            /* Building Test Package */
            chdir($path . "/tests");
            exec("ant debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RunTimeException("Unable to build using [ant debug]");
            }
        }

        /* Installing packages */
        foreach ($androidTestPaths as $path) {
            /* Installing Main Package */
            chdir($path."/bin");
            exec("adb install -r *.apk");


            /* Installing test package */
            chdir($path."/tests/bin");
            exec("adb install -r *-debug.apk");
        }

        /* Running tests after parsing test package name */
        foreach ($androidTestPaths as $path) {
            chdir($path."/tests");

            $xml = simplexml_load_file("AndroidManifest.xml");

            $testPackage = $xml->attributes()->package;

            $testCommand = "adb shell am instrument -w ".$testPackage."/android.test.InstrumentationTestRunner";

            exec($testCommand, $testOutput, $result);
            $testResult = $this->parseAndroidOutput($testOutput);
            $resultArray = array_merge($resultArray, $testResult);

            if ($result != 0) {
                throw new RunTimeException("Unable to run command [".$testCommand."]"."\n".$testOutput);
            }
        }

        return $resultArray;
    }

    private function parseiOSOutput($testResults) {
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

    private function parseAndroidOutput($testOutput) {

        /* Parsing output from test program.
        Currently Android InstrumentationTestRunner does give option of nicely format output for report */

        $state = 0;
        $userData = null;
        $result = null;
        $resultArray = array();

        foreach ($testOutput as $line) {

            switch ($state) {
                /* Looking for test name */
                case 0:
                if ($line == "") { break; }

                /* Parsing Failures
                There can be several failures in one report */
                $test = strstr($line, "Failure");
                if ($test != false) {
                    if ($result == null) {
                        $result = new ArcanistUnitTestResult();
                        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                    }

                    /* Getting test name. Should be in third position */
                    strtok($test, ' ');
                    strtok(' ');
                    $testName = strtok(' ');
                    $testName = str_replace(':', '', $testName);

                    /* Making sure not to grab this line which also contains Failure string */
                    if ($testName != "Errors") {
                        $state = 1;
                        $result->setName($testName);
                    }

                    break;
                }

                /* Parsing OK
                There can be several failures or 1 OK in report*/
                $test = strstr($line, "OK (");
                if ($test != false) {
                    $result = new ArcanistUnitTestResult();
                    $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
                    $result->setName("All Unit Tests");
                    array_push($resultArray, $result);
                }

                /* Parsing Error */
                $test = strstr($line, "Error in ");
                if ($test != false) {
                    $result = new ArcanistUnitTestResult();
                    $result->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
                    $state = 1;
                }

                /* Looking for stack trace */
                case 1:

                /* Reached the end of trace */
                if ($line == "") {
                    $result->setUserData($userData);
                    array_push($resultArray, $result);
                    $result = null;
                    $userData = null;

                    $state = 0;
                } else {
                    $userData .= $line."\n";
                }

                break;

                default:
                break;
            }
        }

        return $resultArray;
    }
}
