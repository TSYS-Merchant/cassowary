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
 * Utilize the Android lint utilities that come with the latest ADT.
 * The engine assumes that the ADT binaries are installed on the user's
 * machine and available on the path.
 *
 * @group linter
 */
final class AndroidLintEngine extends ArcanistLintEngine {
    public function buildLinters() {
        $linters = array();
        $paths = $this->getPaths();

        $linters[] = id(new ArcanistFilenameLinter())->setPaths($paths);

        // skip directories and lint only regular files in remaining linters
        foreach ($paths as $key => $path) {
            if ($this->getCommitHookMode()) {
                continue;
            }

            if (!is_file($this->getFilePathOnDisk($path))) {
                unset($paths[$key]);
            }
        }

        $android_paths = preg_grep('/\.(java|xml)$/', $paths);
        $linters[] =
                id(new ArcanistGeneratedLinter())->setPaths($android_paths);
        $linters[] = id(new ArcanistNoLintLinter())->setPaths($android_paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($android_paths)
                ->setCustomSeverityMap(
                    array(
                        ArcanistTextLinter::LINT_LINE_WRAP =>
                        ArcanistLintSeverity::SEVERITY_ADVICE
                    ))->setMaxLineLength(120);
        $linters[] = id(new ArcanistSpellingLinter())->setPaths($android_paths);

        // locate project directories and run static analysis
        if (count($android_paths) > 0) {
            $eclipse_paths = array();
            $gradle_path_modules = array();

            foreach ($android_paths as $key => $path) {
                $path_on_disk = $this->getFilePathOnDisk($path);
                $current_directory = dirname($path_on_disk);
                $eclipse_path = null;
                $gradle_path = null;
                $gradle_modules = array();

                do {
                    if ($current_directory === '/'
                            || $current_directory === 'C:\\'
                    ) {
                        break;
                    }

                    if (file_exists($current_directory.'/project.properties')) {
                        // Eclipse project root
                        $eclipse_path = $current_directory;
                    } else if (file_exists($current_directory.'/gradlew')) {
                        // Gradle project root
                        $gradle_path = $current_directory;
                    } else if (file_exists($current_directory.'/build.gradle')) {
                        // Gradle module root
                        $gradle_modules[] = basename($current_directory);
                    }

                    $current_directory = dirname($current_directory);
                } while (empty($eclipse_path) && empty($gradle_path));

                if ($eclipse_path != null
                        && !in_array($eclipse_path, $eclipse_paths)
                        && preg_match('/tests$/', $eclipse_path) == 0
                ) {
                    $eclipse_paths[] = $eclipse_path;
                }

                if ($gradle_path != null) {
                    if (!isset($gradle_path_modules[$gradle_path])) {
                        $gradle_path_modules[$gradle_path] = array();
                    }

                    foreach ($gradle_modules as $module) {
                        if (!in_array($module, $gradle_path_modules[$gradle_path])) {
                            $gradle_path_modules[$gradle_path][] = $module;
                        }
                    }
                }
            }

            $linters[] = id(new ArcanistAndroidLinter(null))
                    ->setPaths($eclipse_paths);

            foreach ($gradle_path_modules as $path => $modules) {
                $linters[] = id(new ArcanistAndroidLinter($modules))
                        ->setPaths(array($path));
            }
        }

        // allow for copyright license to be enforced for projects that opt in
        $check_copyright =
                $this->getWorkingCopy()->getProjectConfig('check_copyright');
        if ($check_copyright) {
            $java_paths = preg_grep('/\.java$/', $paths);
            $linters[] =
                    id(new ArcanistCustomLicenseLinter())->setPaths($java_paths);
        }

        return $linters;
    }
}
