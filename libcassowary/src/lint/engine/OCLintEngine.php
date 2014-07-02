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
* Utilize the OCLint lint utilities. The engine assumes
* that OCLint is installed on the user's machine and available
* on the path.
*
* @group linter
*/
final class OCLintEngine extends ArcanistLintEngine {
    public function buildLinters() {
        $linters = array();
        $paths = $this->getPaths();

        $linters[] = id(new ArcanistOCFilenameLinter())->setPaths($paths);

        // skip directories and lint only regular files in remaining linters
        foreach ($paths as $key => $path) {
            if ($this->getCommitHookMode()) {
                continue;
            }

            if (!is_file($this->getFilePathOnDisk($path))) {
                unset($paths[$key]);
            }
        }

        $text_paths = preg_grep('/\.(h|m|sh|pch)$/', $paths);
        $linters[] = id(new ArcanistGeneratedLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($text_paths)
                     ->setCustomSeverityMap(
                         array(
                             ArcanistTextLinter::LINT_LINE_WRAP =>
                                 ArcanistLintSeverity::SEVERITY_ADVICE
                         ))->setMaxLineLength(120);
        $linters[] = id(new ArcanistSpellingLinter())->setPaths($text_paths);

        $implementation_paths = preg_grep('/\.m$/', $paths);
        $linters[] = id(new ArcanistOCLinter())->setPaths($implementation_paths);

        // locate project directories and run static analysis
        if (count($implementation_paths) > 0) {
            $analysisPaths = array();

            foreach ($implementation_paths as $key => $path) {
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

                        // if an oclint.sh file can be found we know
                        // we're in the correct place
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

        // allow for copyright license to be enforced for projects that opt in
        $check_copyright =
                $this->getWorkingCopy()->getProjectConfig('check_copyright');
        if ($check_copyright) {
            $header_paths = preg_grep('/\.h$/', $paths);
            $linters[] = id(new ArcanistCustomLicenseLinter())->setPaths($header_paths);
        }

        return $linters;
    }
}
