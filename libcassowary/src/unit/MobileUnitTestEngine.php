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
        $result_array = array();
        $ios_test_paths = array();
        $android_test_paths = array();

        // Looking for project root directory
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot."/".$path;

            // Checking all levels of path
            do {
                // Project root should have .xctool-args
                // Only add path once per project
                if (file_exists($root_path."/.xctool-args")
                && !in_array($root_path, $ios_test_paths)) {
                    array_push($ios_test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, "/");
                $root_path = substr_replace($root_path, "", strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot."/".$path;

            // Checking all levels of path
            do {
                // Project root should have AndroidManifest.xml
                // We only want projects that have tests
                // Only add path once per project
                if (file_exists($root_path."/AndroidManifest.xml")
                && file_exists($root_path."/tests")
                && !in_array($root_path, $android_test_paths)) {
                    array_push($android_test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, "/");
                $root_path = substr_replace($root_path, "", strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        // Checking to see if no paths were added
        if (count($ios_test_paths) == 0 && count($android_test_paths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        // Trying to build for every project
        foreach ($ios_test_paths as $path) {
            chdir($path);

            $result_location = tempnam(sys_get_temp_dir(), 'arctestresults.phab');
            exec(phutil_get_library_root("libcassowary").
              "/../../externals/xctool/xctool.sh -reporter phabricator:".$result_location." test");
            $test_results = json_decode(file_get_contents($result_location), true);
            unlink($result_location);

            $test_result = $this->parseiOSOutput($test_results);

            $result_array = array_merge($result_array, $test_result);
        }

        foreach ($android_test_paths as $path) {
            // Checking For and Updating Library Projects
            $library_paths = array();
            chdir($path);
            $properties = file('project.properties', FILE_SKIP_EMPTY_LINES);
            foreach ($properties as $item) {
                if (strpos($item, 'android.library.reference') !== false) {
                    $library_path = substr($item, strpos($item, "=") + 1);
                    $library_path = chop($library_path);
                    array_push($library_paths, $library_path);
                }
            }
            if (count($library_paths) > 0) {
                foreach ($library_paths as $library_path) {
                    chdir($path);
                    chdir($library_path);
                    list ($err, $stdout, $stderr) = exec_manual('android update project --path . --subprojects');
                }
            }

            // Building Main Package
            chdir($path);
            exec("android update project --path .");

            $output = array();
            $result = 0;
            exec("ant debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RuntimeException("Unable to build using [ant debug]");
            }

            // Building Test Package
            chdir($path . "/tests");
            exec("android update test-project --path . -m ..");
            exec("ant debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RuntimeException("Unable to build using [ant debug]");
            }
        }

        // Installing packages
        foreach ($android_test_paths as $path) {
            // Installing Main Package
            chdir($path."/bin");
            exec("adb install -r *.apk");


            // Installing test package
            chdir($path."/tests/bin");
            exec("adb install -r *-debug.apk");
        }

        // Running tests after parsing test package name
        foreach ($android_test_paths as $path) {
            chdir($path."/tests");

            $xml = simplexml_load_file("AndroidManifest.xml");

            $test_package = $xml->attributes()->package;

            $test_command = "adb shell am instrument -w ".$test_package."/android.test.InstrumentationTestRunner";

            $test_output = array();
            $result = 0;
            exec($test_command, $test_output, $result);
            $test_result = $this->parseAndroidOutput($test_output);
            $result_array = array_merge($result_array, $test_result);

            if ($result != 0) {
                throw new RuntimeException("Unable to run command [".$test_command."]"."\n".$test_output);
            }
        }

        return $result_array;
    }

    private function parseiOSOutput($test_results) {
        $result = null;
        $result_array = array();

        // Get build output directory, run gcov, and parse coverage results
        // for all implementations
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

        // Iterate through test results and locate passes / failures
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

    private function parseAndroidOutput($test_output) {
        $state = 0;
        $user_data = null;
        $result = null;
        $result_array = array();

        foreach ($test_output as $line) {

            switch ($state) {
                // Looking for test name
                case 0:
                if ($line == "") { break; }

                // Parsing Failures
                // There can be several failures in one report
                $test = strstr($line, "Failure");
                if ($test != false) {
                    if ($result == null) {
                        $result = new ArcanistUnitTestResult();
                        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                    }

                    strtok($test, ' ');
                    strtok(' ');
                    $testName = strtok(' ');
                    $testName = str_replace(':', '', $testName);

                    // Making sure not to grab this line which
                    // also contains Failure string
                    if ($testName != "Errors") {
                        $state = 1;
                        $result->setName($testName);
                    }

                    break;
                }

                // Parsing OK
                // There can be several failures or 1 OK in report
                $test = strstr($line, "OK (");
                if ($test != false) {
                    $result = new ArcanistUnitTestResult();
                    $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
                    $result->setName("All Unit Tests");
                    array_push($result_array, $result);
                }

                // Parsing Error
                $test = strstr($line, "Error in ");
                if ($test != false) {
                    $result = new ArcanistUnitTestResult();
                    $result->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
                    $state = 1;
                }

                // Looking for stack trace
                case 1:

                // Reached the end of trace
                if ($line == "") {
                    $result->setUserData($user_data);
                    array_push($result_array, $result);
                    $result = null;
                    $user_data = null;

                    $state = 0;
                } else {
                    $user_data .= $line."\n";
                }

                break;

                default:
                break;
            }
        }

        return $result_array;
    }
}
