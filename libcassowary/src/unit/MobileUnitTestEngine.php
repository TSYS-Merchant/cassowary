<?php

/*
Copyright 2012-2018 iMobile3, LLC. All rights reserved.

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
 * and combines them into one unified use case.
 *
 * To use, set unit_engine in .arcconfig, or use --engine flag
 * with arc unit.
 *
 * @group unitrun
 */
final class MobileUnitTestEngine extends ArcanistUnitTestEngine {
    private $projectRoot;
    private $androidOnly = false;

    public function run() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $result_array = array();
        $ios_test_paths = array();
        $android_test_paths = array();
        $dotnet_test_paths = array();
        $current_user = get_current_user();

        // Looking for project root directory

        // iOS (xcode)
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot.'/'.$path;

            // Checking all levels of path
            do {
                // Project root should have .xctool-args
                // Only add path once per project
                if (file_exists($root_path.'/.xctool-args')
                        && !in_array($root_path, $ios_test_paths)
                ) {
                    array_push($ios_test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, '/');
                $root_path = substr_replace($root_path, '',
                    strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        // Android (gradle)
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot.'/'.$path;

            // Checking all levels of path
            do {
                $initial_path = $root_path;
                // module should contain an .iml file
                // and a build.gradle file
                // and we only want modules that have unit tests
                // Only add path once per project
                if (count(glob($root_path.'/*.iml')) > 0
                        && file_exists($root_path.'/build.gradle')
                        && file_exists($root_path.'/src/test')
                        && !in_array($root_path, $android_test_paths)) {
                    array_push($android_test_paths, $root_path);
                }

                $parent_dir = dirname($root_path);
                if ($parent_dir != '') {
                    $root_path = $parent_dir;
                }

            } while ($initial_path != $parent_dir);
        }

        // .NET
        foreach ($this->getPaths() as $path) {
            $root_path = $this->projectRoot.'/'.$path;

            // Checking all levels of path
            do {
                // Project root should have .xunit-args
                // Only add path once per project
                if (file_exists($root_path.'/.xunit-args')
                        && !in_array($root_path, $dotnet_test_paths)
                ) {
                    array_push($dotnet_test_paths, $root_path);
                }

                // Stripping last level
                $last = strrchr($root_path, '/');
                $root_path = substr_replace($root_path, '',
                    strrpos($root_path, $last), strlen($last));
            } while ($last);
        }

        // Checking to see if no paths were added
        if (count($ios_test_paths) == 0
                && count($android_test_paths) == 0
                && count($dotnet_test_paths) == 0) {
            throw new ArcanistNoEffectException('No tests to run.');
        }

        if (count($ios_test_paths) == 0
                    && count($android_test_paths) > 0) {
            $this->androidOnly = true;
        }

        // Trying to build for every project

        // iOS
        foreach ($ios_test_paths as $path) {
            chdir($path);

            $result_location =
                    tempnam(sys_get_temp_dir(), 'arctestresults.phab');

            $xcodebuild_params = $this->getXcodebuildArgs();
            exec('xcodebuild '.$xcodebuild_params.' build-for-testing');

            exec('xctool -reporter phabricator:'
            .$result_location.' run-tests');
            $test_results =
                    json_decode(file_get_contents($result_location), true);
            unlink($result_location);

            $test_result = $this->parseiOSOutput($test_results);

            if ($test_result) {
                $result_array = array_merge($result_array, $test_result);
            }
        }

        // .NET
        foreach ($dotnet_test_paths as $path) {
            chdir($path);

            // Get config file
            $config_file =
                json_decode(file_get_contents('.xunit-args'), true);
            $runner = $config_file['xunit_runner_path'];
            $test_projects = $config_file['test_project_paths'];
            $traits = $config_file['xunit_traits'];

            // run unit tests, by project
            foreach ($test_projects as $test_project) {
                $result_location =
                tempnam(sys_get_temp_dir(), 'arctestresults.phab');

                shell_exec($runner.' '.$test_project.' '.$traits.
                ' -quiet -xml '.$result_location);
                $test_results = file_get_contents($result_location);

                unlink($result_location);

                $test_result = $this->parseDotNetOutput($test_results);

                if ($test_result) {
                    $result_array = array_merge($result_array, $test_result);
                }
            }
        }

        // Android (gradle)
        if (count($android_test_paths) > 0) {
            $output_array = array();
            $return_value = 0;
            $result_array = array();

            $result = null;
            $start_time = null;
            $end_time = null;
            $test_duration = null;

            foreach ($android_test_paths as $path) {
                $cmd = ':'.basename($path);
                do {
                    $parent_dir = dirname($path);
                    // In case Application module is organized by folder E.g:
                    // hardware:swiper
                    if (!$this->isAndroidProjectRootDirectory($parent_dir)) {
                        $cmd = ':'.basename($parent_dir).$cmd;
                        $path = $parent_dir;
                    }
                } while (!$this->isAndroidProjectRootDirectory($parent_dir));

                $module = $cmd;
                $cmd = './gradlew '.$cmd.':test';
                $start_time = microtime(true);

                passthru($cmd, $return_value);

                $end_time = microtime(true);

                $test_duration = ($end_time - $start_time) * 1000;

                $result = new ArcanistUnitTestResult();

                $result
                    ->setName("$module")
                    ->setUserData($current_user)
                    ->setDuration($test_duration);

                if ($return_value == 0) {
                    $result
                        ->setResult(ArcanistUnitTestResult::RESULT_PASS);
                    $result_array[] = $result;
                } else if ($return_value == -1) {
                    $result
                        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
                    $result_array[] = $result;
                } else if ($return_value == 1) {
                    $result
                        ->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                    $result_array[] = $result;
                }
            }
        }

        return $result_array;
    }

    private function isAndroidProjectRootDirectory($module_path) {
        $is_android_root_directory = false;
        if ($module_path == '') {
            return $is_android_root_directory;
        } else {
            // Gradle projects are structured such that the project root
            // contains the following files:
            // - *.iml: Module config file. Required for a directory to
            //   be considered a buildable gradle module.
            // - settings.gradle: File automatically generated by Gradle,
            //   only available at project root's directory.
            // - gradlew: Executable gradle for command line only available
            //   at project root's directory.
            if ($module_path != '' && count(glob($module_path.'/*.iml')) == 1
                && count(glob($module_path.'/build.gradle')) == 1
                && count(glob($module_path.'/settings.gradle')) == 1
                && count(glob($module_path.'/gradlew')) == 1) {
                    $is_android_root_directory = true;
            }
        }

        return $is_android_root_directory;
    }

    private function parseiOSOutput($test_results) {
        $result = null;
        $result_array = array();

        // Get build output directory, run gcov, and parse coverage results
        // for all implementations
        $build_dir_output = array();
        $_ = 0;
        $xctoolargs_params = $this->getXcodebuildArgsForCodeCoverage();

        $targets = array();

        $cmd = 'xcodebuild -list | grep TestsHost -m1 ';
        exec($cmd, $targets, $_);

        if ($_ == 0) {
            $xctoolargs_params .= ' -target '.trim($targets[0]);
        }

        $cmd = 'xcodebuild -showBuildSettings '
               .$xctoolargs_params
               .' | grep OBJECT_FILE_DIR_normal -m1 | cut -d = -f2';
        exec($cmd, $build_dir_output, $_);
        if ($_ != 0) {
            $cmd = 'xcodebuild -showBuildSettings '
                    .$xctoolargs_params
                    .' | grep TARGET_TEMP_DIR -m1 | cut -d = -f2';
            $_ = 0;
            exec($cmd, $build_dir_output, $_);
            $build_dir_output[0] .= '/Objects-normal';
        }

        $build_dir_output[0] = trim($build_dir_output[0]);
        if (file_exists($build_dir_output[0].'/x86_64')) {
            $build_dir_output[0] .= '/x86_64/';
        } else {
            $build_dir_output[0] .= '/i386/';
        }

        if (chdir($build_dir_output[0]) == false) {
            return;
        }
        exec('gcov * > /dev/null 2> /dev/null');

        $coverage = array();
        foreach (glob('*.m.gcov') as $gcov_filename) {
            $str = '';

            foreach (file($gcov_filename) as $gcov_line) {
                $gcov_matches = array();
                if ($g = preg_match_all('/.*?(.):.*?(\\d+)/is', $gcov_line,
                            $gcov_matches)
                        && $gcov_matches[2][0] > 0
                ) {
                    if ($gcov_matches[1][0] === '#'
                            || $gcov_matches[1][0] === '='
                    ) {
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
                if (strpos($path, str_replace('.gcov', '', $gcov_filename))
                        !== false
                ) {
                    $coverage[$path] = $str;
                }
            }
        }

        // Iterate through test results and locate passes / failures
        foreach ($test_results as $key => $test_result_item) {
            $result = new ArcanistUnitTestResult();
            $result->setResult($test_result_item['result']);
            $result->setName($test_result_item['name']);
            $result->setDuration((float)$test_result_item['duration']);
            $result->setUserData($test_result_item['userdata']);
            $result->setExtraData($test_result_item['extra']);
            $result->setLink($test_result_item['link']);
            $result->setCoverage($coverage);
            array_push($result_array, $result);
        }

        return $result_array;
    }

    private function parseDotNetOutput($test_results) {
        $result = null;
        $result_array = array();

        // Iterate through xunit results and locate passes / failures
        $xml = simplexml_load_string($test_results);
        $xunit_result_array = $xml->xpath('//test');

        foreach ($xunit_result_array as $key => $xunit_result) {
            $result = new ArcanistUnitTestResult();
            $result->setResult(ArcanistUnitTestResult::RESULT_PASS);

            $result_name = (string)$xunit_result['name'];
            if (strlen($result_name) > 200) {
                $result_name = substr($result_name, 0, 200);
            }

            $result->setName($result_name);

            $result->setDuration((float)$xunit_result['time']);

            if ($xunit_result['result'] == 'Fail') {
                $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                $result->setUserData(
                    (string)$xunit_result['failure']['message']);
                $result->setExtraData(array(
                    'stack-trace',
                    (string)$xunit_result['failure']['stack-trace'],
                ));
            }

            array_push($result_array, $result);
        }

        return $result_array;
    }

    // Retrieve Args from the xctool-args file for code coverage
    private function getXcodebuildArgsForCodeCoverage() {
        $xctoolargs_path = getcwd().'/.xctool-args';
        $buildargs = [];
        if (file_exists($xctoolargs_path)) {
            $buildargs = json_decode(file_get_contents($xctoolargs_path));
        } else {
            array_push($buildargs, '-sdk', 'iphonesimulator');
        }

        // Return Args as string, escaping the option values
        for ($x = 0; $x < count($buildargs); $x++) {
            if ($x % 2 == 0) {
                // Code coverage relies on the main target, not the unit tests
                // so we must filter out anything that directs xcodebuild to
                // the unit tests, otherwise we only get code coverage of the
                // unit tests themselves.
                if ($buildargs[$x] == '-find-target' ||
                    $buildargs[$x] == '-target' ||
                    $buildargs[$x] == '-project') {
                    $buildargs[$x] = '';
                    $buildargs[$x + 1] = '';
                }
            } else {
                if ($buildargs[$x] != '') {
                    $buildargs[$x] = escapeshellarg($buildargs[$x]);
                }
            }
        }
        // Append required -arch flag where -destination or -arch is not set
        $destination_not_in_args = !in_array('-destination', $buildargs, false);
        $architecture_not_in_args = !in_array('-arch', $buildargs, false);
        if ($destination_not_in_args && $architecture_not_in_args) {
            array_push($buildargs, '-arch', 'i386');
        }
        return implode(' ', $buildargs);
    }

    // Retrieve Args from the xctool-args file
    private function getXcodebuildArgs() {
        $xctoolargs_path = getcwd().'/.xctool-args';
        $buildargs = [];
        if (file_exists($xctoolargs_path)) {
            $buildargs = json_decode(file_get_contents($xctoolargs_path));
        } else {
            array_push($buildargs, '-sdk', 'iphonesimulator');
        }

        // Return Args as string, escaping the option values
        for ($x = 0; $x < count($buildargs); $x++) {
            if ($x % 2 == 0) {
                if ($buildargs[$x] == '-target' ||
                    $buildargs[$x] == '-project') {
                    $buildargs[$x] = '';
                    $buildargs[$x + 1] = '';
                } else if ($buildargs[$x] == '-find-target') {
                    $buildargs[$x] = '-scheme';
                }
            } else {
                if ($buildargs[$x] != '') {
                    $buildargs[$x] = escapeshellarg($buildargs[$x]);
                }
            }
        }
        // Append required -arch flag where -destination or -arch is not set
        $destination_not_in_args = !in_array('-destination', $buildargs, false);
        $architecture_not_in_args = !in_array('-arch', $buildargs, false);
        if ($destination_not_in_args && $architecture_not_in_args) {
            array_push($buildargs, '-arch', 'i386');
        }
        return implode(' ', $buildargs);
    }

    public function shouldEchoTestResults() {
        return !$this->androidOnly;
    }
}
