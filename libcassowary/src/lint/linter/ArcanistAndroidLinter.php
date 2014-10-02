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
 * Uses Android Lint to detect various errors in Java code. To use this linter,
 * you must install the Android SDK and configure which codes you want to be
 * reported as errors, warnings and advice.
 *
 * @group linter
 */
final class ArcanistAndroidLinter extends ArcanistLinter {
    private $gradleModules;

    public function __construct($modules) {
        $this->gradleModules = $modules;
    }

    private function getLintPath() {
        $lint_bin = "lint";

        list($err, $stdout) = exec_manual('which %s', $lint_bin);
        if ($err) {
            throw new ArcanistUsageException("Lint does not appear to be "
            . "available on the path. Make sure that the Android tools "
            . "directory is part of your path.");
        }

        return trim($stdout);
    }

    private function getGradlePath()
    {
        $gradle_bin = "gradle";

        list($err, $stdout) = exec_manual('which %s', $gradle_bin);
        if ($err) {
            throw new ArcanistUsageException("Gradle does not appear to be "
                .'available on the path.');
        }

        return trim($stdout);
    }

    private function runGradle($path)
    {
        $gradle_bin = join('/', array(rtrim($path, '/'), "gradlew"));
        if (!file_exists($gradle_bin)) {
            $gradle_bin = $this->getGradlePath();
        }

        $cwd = getcwd();
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
        chdir($path_on_disk);
        $lint_command = '';
        $output_paths = array();
        foreach ($this->gradleModules as $module) {
            $lint_command .= ':'.$module.':lint ';
            $output_paths[] = $path.'/'.$module.'/build/outputs/lint-results.xml';
        }
        list($err) = exec_manual($gradle_bin.' '.$lint_command);
        chdir($cwd);

        if ($err) {
            throw new ArcanistUsageException("Error executing gradle command");
        }

        return $output_paths;
    }

    private function runLint($path)
    {
        $lint_bin = $this->getLintPath();
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
        $arc_lint_location = tempnam(sys_get_temp_dir(), 'arclint.xml');

        list($err) = exec_manual(
            '%s --showall --nolines --fullpath --quiet --xml %s %s',
            $lint_bin, $arc_lint_location, $path_on_disk);
        if ($err != 0 && $err != 1) {
            throw new ArcanistUsageException("Error executing lint command");
        }

        return array($arc_lint_location);
    }

    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'AndroidLint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    protected function shouldLintDirectories() {
        return true;
    }

    public function lintPath($path) {
        $lint_bin = $this->getLintPath();
        $extra_rule_project_path = phutil_get_library_root('libcassowary') .
                '/../support/lint/android';
        $extra_rule_jar_file = dirname(__FILE__) .
                '/rules/CassowaryAndroidLintCustomRules.jar';
        $lint_lib_path = dirname($lint_bin) . '/lib';

        chdir($extra_rule_project_path);
        list($err) = exec_manual('ant jar -lib %s', $lint_lib_path);
        if ($err) {
            throw new ArcanistUsageException("Error compiling lint rules");
        }

        putenv('ANDROID_LINT_JARS=' . $extra_rule_jar_file);

        if (!empty($this->gradleModules)) {
            $lint_xml_files = $this->runGradle($path);
        } else {
            $lint_xml_files = $this->runLint($path);
        }

        $messages = array();
        foreach ($lint_xml_files as $file) {
            $filexml = simplexml_load_string(file_get_contents($file));

            if ($filexml->attributes()->format < 4) {
                throw new ArcanistUsageException('Unsupported Android lint '
                .'output version. Please update your Android SDK to the '
                .'latest version.');
            } else if ($filexml->attributes()->format > 4) {
                throw new ArcanistUsageException('Unsupported Android lint '
                .'output version. Cassowary needs an update to match.');
            }

            foreach ($filexml as $issue) {
                $loc_attrs = $issue->location->attributes();
                $issue_attrs = $issue->attributes();

                $message = new ArcanistLintMessage();
                $message->setPath((string)$loc_attrs->file);
                // Line number and column are irrelevant for
                // artwork and other assets
                if (isset($loc_attrs->line)) {
                    $message->setLine(intval($loc_attrs->line));
                }
                if (isset($loc_attrs->column)) {
                    $message->setChar(intval($loc_attrs->column));
                }
                $message->setName((string)$issue_attrs->id);
                $message->setCode((string)$issue_attrs->category);
                $message->setDescription(preg_replace('/^\[.*?\]\s*/', '',
                    $issue_attrs->message));

                // Setting Severity
                if ($issue_attrs->severity == 'Error'
                        || $issue_attrs->severity == 'Fatal'
                ) {
                    $message->setSeverity(
                        ArcanistLintSeverity::SEVERITY_ERROR);
                } else if ($issue_attrs->severity == 'Warning') {
                    $message->setSeverity(
                        ArcanistLintSeverity::SEVERITY_WARNING);
                } else {
                    $message->setSeverity(
                        ArcanistLintSeverity::SEVERITY_ADVICE);
                }

                // Skip line number check, since we're linting the whole project
                $message->setBypassChangedLineFiltering(true);

                $messages[$message->getPath().':'
                .$message->getLine().':'
                .$message->getChar().':'
                .$message->getName().':'
                .$message->getDescription()] = $message;
            }

            unlink($file);
        }

        foreach ($messages as $message) {
            $this->addLintMessage($message);
        }

        putenv('_JAVA_OPTIONS');
    }
}
