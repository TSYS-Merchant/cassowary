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
* Perform basic linting on Windows .NET files.
*
* @group linter
*/
final class DotNetLintEngine extends ArcanistLintEngine {
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

        $text_paths = preg_grep('/\.(cs|cshtml|vb|vbhtml|sql)$/', $paths);
        $linters[] = id(new ArcanistMobileGeneratedLinter())
                     ->setPaths($text_paths);
        $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistTextLinter())->setPaths($text_paths)
                     ->setCustomSeverityMap(
                         array(
                             ArcanistTextLinter::LINT_DOS_NEWLINE =>
                                 ArcanistLintSeverity::SEVERITY_DISABLED,
                             ArcanistTextLinter::LINT_BAD_CHARSET =>
                                 ArcanistLintSeverity::SEVERITY_DISABLED,
                             ArcanistTextLinter::LINT_LINE_WRAP =>
                                 ArcanistLintSeverity::SEVERITY_ADVICE
                         ))->setMaxLineLength(250);
        $linters[] = id(new ArcanistSpellingLinter())->setPaths($text_paths);
        $linters[] = id(new ArcanistReSharperLinter())->setPaths($text_paths);

        // allow for copyright license to be enforced for projects that opt in
        $check_copyright =
                $this->getWorkingCopy()->getProjectConfig('check_copyright');
        if ($check_copyright) {
            $header_paths = preg_grep('/\.(cs|vb)$/', $paths);
            $linters[] = id(new ArcanistCustomLicenseLinter())->setPaths($header_paths);
        }

        return $linters;
    }
}
