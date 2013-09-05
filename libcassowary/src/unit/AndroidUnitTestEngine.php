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
* AndroidUnit wrapper
*
* To use, set unit_engine in .arcconfig, or use --engine flag
* with arc unit. Currently supports only class & test files
* (no directory support). Runs on top of android.test.InstrumentionTestRunner.
*
* @group unitrun
*/
final class AndroidUnitTestEngine extends ArcanistBaseUnitTestEngine {
    private $projectRoot;

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $result_array = array();
        $test_paths = array();

        // Looking for project root directory
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot."/".$path;

            // Checking all levels of path
            do {
                // Project root should have AndroidManifest.xml
                // We only want projects that have tests
                // Only add path once per project
                if (file_exists($root_path."/AndroidManifest.xml")
                && file_exists($root_path."/tests")
                && !in_array($root_path, $test_paths)) {
                    array_push($test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, "/");
                $root_path = substr_replace($root_path, "", strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        // Checking to see if no paths were added
        if (count($test_paths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        // Trying to build for every project
        foreach ($test_paths as $path) {
            // Building Main Package
            chdir($path);
            exec("ant clean");
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
            exec("ant debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RuntimeException("Unable to build using [ant debug]");
            }
        }

        // Installing packages
        foreach ($test_paths as $path) {
            // Installing Main Package
            chdir($path."/bin");
            exec("adb install -r *.apk");


            // Installing test package
            chdir($path."/tests/bin");
            exec("adb install -r *-debug.apk");
        }

        // Running tests after parsing test package name
        foreach ($test_paths as $path) {
            chdir($path."/tests");

            $xml = simplexml_load_file("AndroidManifest.xml");

            $test_package = $xml->attributes()->package;

            $test_command = "adb shell am instrument -w ".$test_package."/android.test.InstrumentationTestRunner";

            $test_output = array();
            $result = 0;
            exec($test_command, $test_output, $result);
            $test_result = $this->parseOutput($test_output);
            $result_array = array_merge($result_array, $test_result);

            if ($result != 0) {
                throw new RuntimeException("Unable to run command [".$test_command."]"."\n".$test_output);
            }
        }

        return $result_array;
    }

    private function parseOutput($test_output) {

        // Parsing output from test program.
        // Currently Android InstrumentationTestRunner does give option of nicely format output for report

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

                    // Getting test name. Should be in third position
                    strtok($test, ' ');
                    strtok(' ');
                    $test_name = strtok(' ');
                    $test_name = str_replace(':', '', $test_name);

                    // Making sure not to grab this line which also
                    // contains Failure string
                    if ($test_name != "Errors") {
                        $state = 1;
                        $result->setName($test_name);
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
