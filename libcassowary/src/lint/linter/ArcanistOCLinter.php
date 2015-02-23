<?php

/*

Copyright 2012-2015 iMobile3, LLC. All rights reserved.

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
* Uses OCLint to detect various errors in Objective-C code. To use
* this linter, you must install OCLint.
*
* @group linter
*/
final class ArcanistOCLinter extends ArcanistLinter {
    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'ObjectiveCLint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    public function lintPath($path) {
        list($err) = exec_manual('which oclint');
        if ($err) {
            throw new ArcanistUsageException("OCLint does not appear to be "
                ."available on the path. Make sure that the OCLint is "
                ."installed.");
        }

        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
        $currentDirectory = dirname($path_on_disk);
        $ocLintPath = null;

        do {
            if ($currentDirectory === '/') {
                break;
            }

            foreach (new DirectoryIterator($currentDirectory) as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if ($file->getFilename() === 'oclint.sh') {
                    $ocLintPath = $file->getPathname();
                }
            }

            $currentDirectory = dirname($currentDirectory);
        } while (empty($ocLintPath));

        if (empty($ocLintPath)) {
            return;
        }

        try {
            $stdout = array();
            $_ = 0;
            exec("sh $ocLintPath $path_on_disk", $stdout, $_);
            } catch (CommandException $e) {
            $stdout = $e->getStdout();
        }

        foreach ($stdout as $line) {
            $matches = array();
            if ($c = preg_match_all("/((?:\\/[\\w\\.\\-]+)+):(\\d+):(\\d+): (.*?) P(\\d+)((?:[a-zA-Z0-9 ]+))?/is",
                $line, $matches)) {
                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setLine($matches[2][0]);
                $message->setChar($matches[3][0]);
                $message->setCode($matches[4][0]);

                if ($matches[5][0] === 1) {
                    $message->setSeverity(
                        ArcanistLintSeverity::SEVERITY_ERROR);
                } else {
                    $message->setSeverity(
                        ArcanistLintSeverity::SEVERITY_WARNING);
                }

                $message->setName($matches[4][0]);
                $message->setDescription($matches[6][0]);

                $this->addLintMessage($message);
            }
        }
    }
}
