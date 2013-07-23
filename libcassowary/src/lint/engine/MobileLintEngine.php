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
 * (OCUnit, Android, DotNet) and combines them into one unified use case.
 *
 * @group linter
 */
final class MobileLintEngine extends ArcanistLintEngine {
    public function buildLinters() {
        $linters = array();
        $paths = $this->getPaths();

        $ios_paths = preg_grep('/\.(h|m|sh|pch|png|xib|jpg)$/', $paths);
        $linters[] = id(new ArcanistOCFilenameLinter())->setPaths($ios_paths);

        $non_ios_paths = preg_grep('/\.(h|m|sh|pch|png|xib|jpg)$/', $paths, PREG_GREP_INVERT);
        $linters[] = id(new ArcanistFilenameLinter())->setPaths($non_ios_paths);

        // skip directories and lint only regular files in remaining linters
        foreach ($paths as $key => $path) {
            if ($this->getCommitHookMode()) {
                continue;
            }

            if (!is_file($this->getFilePathOnDisk($path))) {
                unset($paths[$key]);
            }
        }

        $text_paths = preg_grep('/\.(cs|cshtml|vb|vbhtml|sql|h|m|sh|pch|java|xml|php|css|js)$/', $paths);
        $linters[] = id(new ArcanistGeneratedLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistSpellingLinter())->setPaths($text_paths);

        $ios_text_paths = preg_grep('/\.(h|m|sh|pch)$/', $paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($ios_text_paths)
                ->setCustomSeverityMap(
                    array(
                        ArcanistTextLinter::LINT_LINE_WRAP =>
                        ArcanistLintSeverity::SEVERITY_ADVICE
                    ))->setMaxLineLength(120);

        $ios_implementation_paths = preg_grep('/\.m$/', $paths);
        $linters[] = id(new ArcanistOCLinter())->setPaths($ios_implementation_paths);

        // locate project directories and run static analysis
        if (count($ios_implementation_paths) > 0) {
            $analysisPaths = array();

            foreach ($ios_implementation_paths as $key => $path) {
                $path_on_disk = $this->getFilePathOnDisk($path);
                $currentDirectory = dirname($path_on_disk);
                $analysisPath = null;

                do {
                    if ($currentDirectory === '/') {
                        break;
                    }

                    foreach (new DirectoryIterator($currentDirectory) as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }

                        // if an oclint.sh file can be found we know we're in the correct place
                        if ($file->getFilename() === 'oclint.sh') {
                            $analysisPath = $file->getPath();
                        }
                    }

                    $currentDirectory = dirname($currentDirectory);
                } while (empty($analysisPath));

                if ($analysisPath != null && !in_array($analysisPath, $analysisPaths)) {
                    $analysisPaths[] = $analysisPath;
                }
            }

            $linters[] = id(new ArcanistOCStaticAnalysisLinter())->setPaths($analysisPaths);
        }

        $android_paths = preg_grep('/\.(java|xml)$/', $paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($android_paths)
                ->setCustomSeverityMap(
                    array(
                        ArcanistTextLinter::LINT_LINE_WRAP =>
                        ArcanistLintSeverity::SEVERITY_ADVICE
                    ))->setMaxLineLength(100);

        // locate project directories and run static analysis
        if (count($android_paths) > 0) {
            $analysisPaths = array();

            foreach ($android_paths as $key => $path) {
                $path_on_disk = $this->getFilePathOnDisk($path);
                $currentDirectory = dirname($path_on_disk);
                $analysisPath = null;

                do {
                    if ($currentDirectory === '/') {
                        break;
                    }

                    foreach (new DirectoryIterator($currentDirectory) as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }

                        // if an AndroidManifest.xml file can be found we know we're in the
                        // correct place
                        if ($file->getFilename() === 'AndroidManifest.xml') {
                            $analysisPath = $file->getPath();
                        }
                    }

                    $currentDirectory = dirname($currentDirectory);
                } while (empty($analysisPath));

                if ($analysisPath != null
                        && !in_array($analysisPath, $analysisPaths)
                        && preg_match('/tests$/', $analysisPath) == 0
                ) {
                    $analysisPaths[] = $analysisPath;
                }
            }

            $linters[] = id(new ArcanistAndroidLinter())->setPaths($analysisPaths);
        }

        $dotnet_paths = preg_grep('/\.(cs|cshtml|vb|vbhtml|sql)$/', $paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($dotnet_paths)
                ->setCustomSeverityMap(
                    array(
                        ArcanistTextLinter::LINT_DOS_NEWLINE =>
                        ArcanistLintSeverity::SEVERITY_DISABLED,
                        ArcanistTextLinter::LINT_BAD_CHARSET =>
                        ArcanistLintSeverity::SEVERITY_DISABLED,
                        ArcanistTextLinter::LINT_LINE_WRAP =>
                        ArcanistLintSeverity::SEVERITY_ADVICE
                    ))->setMaxLineLength(250);

        $web_paths = preg_grep('/\.(php|css|js)$/', $paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($web_paths)
                ->setCustomSeverityMap(
                    array(
                        ArcanistTextLinter::LINT_DOS_NEWLINE =>
                        ArcanistLintSeverity::SEVERITY_DISABLED,
                        ArcanistTextLinter::LINT_BAD_CHARSET =>
                        ArcanistLintSeverity::SEVERITY_DISABLED,
                        ArcanistTextLinter::LINT_LINE_WRAP =>
                        ArcanistLintSeverity::SEVERITY_ADVICE
                    ))->setMaxLineLength(120);

        // locate solution file (.sln) and run ReSharper solution-wide analysis
        if (count($dotnet_paths) > 0) {
            $analysis_paths = array();

            foreach ($dotnet_paths as $key => $path) {
                $path_on_disk = $this->getFilePathOnDisk($path);
                $current_directory = dirname($path_on_disk);
                $analysis_path = null;

                do {
                    if ($current_directory === 'C:\\') {
                        break;
                    }

                    foreach (new DirectoryIterator($current_directory) as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }

                        // if a .sln file can be found we know we're in the correct place
                        if ($file->getExtension() == 'sln') {
                            $analysis_path = $file->getPathname();
                        }
                    }

                    $current_directory = dirname($current_directory);
                } while (empty($analysis_path));

                if ($analysis_path != null && !in_array($analysis_path, $analysis_paths)) {
                    $analysis_paths[] = $analysis_path;
                }
            }

            $linters[] = id(new ArcanistReSharperLinter())->setPaths($analysis_paths);
        }

        $linters[] = id(new ArcanistXHPASTLinter())->setPaths(preg_grep('/\.php$/', $paths));

        $merge_conflict_linter = id(new ArcanistMergeConflictLinter());

        foreach ($paths as $path) {
            $merge_conflict_linter->addPath($path);
            $merge_conflict_linter->addData($path, $this->loadData($path));
        }

        $linters[] = $merge_conflict_linter;

        // allow for copyright license to be enforced for projects that opt in
        $check_copyright = $this->getWorkingCopy()->getConfig('check_copyright');
        if ($check_copyright) {
            $copyright_paths = preg_grep('/\.(cs|vb|java|h|php)$/', $paths);
            $linters[] = id(new ArcanistCustomLicenseLinter())->setPaths($copyright_paths);
        }

        return $linters;
    }
}
