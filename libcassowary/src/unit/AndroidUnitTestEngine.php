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
 * AndroidUnit wrapper
 *
 * To use, set unit_engine in .arcconfig, or use --engine flag
 * with arc unit. Currently supports only class & test files
 * (no directory support). Runs on top of android.test.InstrumentionTestRunner.
 *
 * @group unitrun
 */
final class AndroidUnitTestEngine extends ArcanistUnitTestEngine {
    private $projectRoot;

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $test_paths = array();

        // Looking for project root directory
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot . "/" . $path;

            // Checking all levels of path
            do {
                // Project root should have AndroidManifest.xml
                // We only want projects that have tests
                // Only add path once per project
                if (file_exists($root_path . "/AndroidManifest.xml")
                        && file_exists($root_path . "/tests")
                        && !in_array($root_path, $test_paths)
                ) {
                    array_push($test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, "/");
                $root_path = substr_replace($root_path, "",
                    strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        // Checking to see if no paths were added
        if (count($test_paths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }

        // Trying to build for every project
        foreach ($test_paths as $path) {
            // Checking For and Updating Library Projects
            $library_paths = array();
            chdir($path);
            $properties = file('project.properties', FILE_SKIP_EMPTY_LINES);
            foreach ($properties as $item) {
                if (strpos($item, 'android.library.reference') !== false) {
                    $library_path = substr($item, strpos($item, "=") + 1);
                    $library_path = realpath(chop($library_path));
                    array_push($library_paths, $library_path);
                }
            }
            if (count($library_paths) > 0) {
                foreach ($library_paths as $library_path) {
                    chdir($library_path);
                    list ($err, $stdout, $stderr) =
                            exec_manual('android update project --path . --subprojects');
                }
            }

            // Building Main Package
            chdir($path);
            exec("android update project --path .");

            $output = array();
            $result = 0;
            exec("ant clean debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RuntimeException("Unable to build using [ant debug]");
            }

            // Building Test Package
            chdir($path . "/tests");
            exec("android update test-project --path . -m ..");
            exec("ant clean debug -d", $output, $result);

            if ($result != 0) {
                print_r($output);
                throw new RuntimeException("Unable to build using [ant debug]");
            }
        }

        $devices = array();
        $no_device_message_shown = false;
        do {
            list($err, $out) = exec_manual('adb devices');
            $lines = explode("\n", $out);
            foreach ($lines as $line) {
                $split = explode("\t", trim($line));
                if (count($split) > 1) {
                    $devices[] = $split[0];
                }
            }

            if (!$no_device_message_shown && count($devices) == 0) {
                echo "No device attached. Waiting for device...\n";
                $no_device_message_shown = true;
            }
        } while (count($devices) == 0);

        $device_id = $devices[0];

        // Installing packages
        foreach ($test_paths as $path) {
            // Installing Main Package
            chdir($path . "/bin");
            list($result, $out) =
                    exec_manual("adb -s %s install -r *-debug.apk",
                        $device_id);
            if ($result != 0) {
                $msg = $out;
                $matches = null;
                if (preg_match('/Failure \[?(\w+)\]?/', $out, $matches) > 0) {
                    $msg = $matches[1];
                }
                throw new RuntimeException('Unable to install app APK: '
                . $msg);
            }

            // Installing test package
            chdir($path . "/tests/bin");
            list($result, $out) =
                    exec_manual("adb -s %s install -r *-debug.apk",
                        $device_id);
            if ($result != 0) {
                $msg = $out;
                $matches = null;
                if (preg_match('/Failure \[?(\w+)\]?/', $out, $matches) > 0) {
                    $msg = $matches[1];
                }
                throw new RuntimeException('Unable to install test APK: '
                . $msg);
            }
        }

        // Running tests after parsing test package name
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"));
        $result_array = array();
        foreach ($test_paths as $path) {
            chdir($path . "/tests");

            $xml = simplexml_load_string(
                file_get_contents("AndroidManifest.xml"));

            $test_package = $xml->attributes()->package;

            $test_command = "adb -s $device_id shell am instrument -r -w "
                    . $test_package . "/android.test.InstrumentationTestRunner";

            $pipes = null;
            $process = proc_open($test_command, $descriptorspec, $pipes,
                realpath('./'));

            $test_number = 1;
            $test_total = 0;
            $test_class = '';
            $test_name = '';
            $user_data = null;
            $result = null;

            while ($line = fgets($pipes[1])) {
                if (!preg_match('/^INSTRUMENTATION_(\w+): (.*?)$/',
                    trim($line),
                    $matches)
                ) {
                    continue;
                }

                if ($matches[1] == 'STATUS_CODE') {
                    $status = $matches[2];
                    if ($status == 0) {
                        echo "\033[42m\033[1m PASS \033[0m\033[0m\n";

                        $result = new ArcanistUnitTestResult();
                        $result
                                ->setResult(ArcanistUnitTestResult::RESULT_PASS)
                                ->setName($test_name);
                        $result_array[] = $result;

                        $test_class = '';
                        $test_name = '';
                    } else if ($status == -1) {
                        echo "\033[41m\033[1m BROKEN \033[0m\033[0m\n";

                        $result = new ArcanistUnitTestResult();
                        $result
                                ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
                                ->setName($test_name);
                        $result_array[] = $result;

                        $test_class = '';
                        $test_name = '';
                    } else if ($status == 1) {
                        echo '(' . $test_number . '/' . $test_total . ') ' .
                                $test_name . ' in ' . $test_class . '...';
                    }
                } else if ($matches[1] == 'STATUS') {
                    $fields = explode('=', $matches[2]);
                    if (count($fields) == 2) {
                        if ($fields[0] == 'current') {
                            $test_number = $fields[1];
                        } else if ($fields[0] == 'numtests') {
                            $test_total = $fields[1];
                        } else if ($fields[0] == 'class') {
                            $test_class = $fields[1];
                        } else if ($fields[0] == 'test') {
                            $test_name = $fields[1];
                        }
                    }
                } else if ($matches[1] == 'RESULT') {
                    $fields = explode('=', $matches[2]);
                    if (count($fields) == 2) {
                        if ($fields[0] == 'longMsg') {
                            echo "\033[41m\033[1m FAIL \033[0m\033[0m: "
                                    . $fields[1] . "\n";

                            $result = new ArcanistUnitTestResult();
                            $result
                                    ->setResult(ArcanistUnitTestResult::RESULT_FAIL)
                                    ->setName($test_name);
                            $result_array[] = $result;
                        }
                    }
                }
            }
            proc_close($process);
        }

        return $result_array;
    }

    public function shouldEchoTestResults() {
        return false;
    }
}
