<?php

/*

Copyright 2012-2019 iMobile3, LLC. All rights reserved.

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
* Uses swiftlint to detect various errors in Swift code. To use
* this linter, you must install swiftlint.
*
* @group linter
*/
final class ArcanistSwiftLinter extends ArcanistLinter {
    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'swiftlint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    public function lintPath($path) {
        list($err) = exec_manual('which swiftlint');
        if ($err) {
            throw new ArcanistUsageException('swiftlint does not appear to be '
                .'available on the path. Make sure that the swiftlint is '
                .'installed. `brew install swiftlint`');
        }

        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
        $current_directory = dirname($path_on_disk);
        $swift_lint_path = null;

        do {
            if ($current_directory === '/') {
                break;
            }

            foreach (new DirectoryIterator($current_directory) as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if ($file->getFilename() == '.swiftlint.yml') {
                    $swift_lint_path = $file->getPathname();
                }
            }

            if ($swift_lint_path == null) {
                $current_directory = dirname($current_directory);
            }
        } while (empty($swift_lint_path));

        if (empty($swift_lint_path)) {
            return;
        }

        try {
            $stdout = array();
            $_ = 0;
            exec('cd $current_directory && swiftlint lint $path_on_disk '
                 .'--reporter json',
                 $stdout,
                 $_);
            } catch (CommandException $e) {
            $stdout = $e->getStdout();
        }
        $data = json_decode(implode('', $stdout), true);
        if ($data == null) {
            throw new ArcanistUsageException('swiftlint does not appear to be '
                                             .'configured or failed to output '
                                             .' json results.');
        }

        foreach ($data as $line) {
            $message = new ArcanistLintMessage();
            $message->setPath($line['file']);
            $message->setLine($line['line']);
            $message->setChar($line['character']);
            $message->setCode($line['rule_id']);

            if ($line['severity'] == 'Error') {
                $message->setSeverity(
                    ArcanistLintSeverity::SEVERITY_ERROR);
            } else {
                $message->setSeverity(
                    ArcanistLintSeverity::SEVERITY_WARNING);
            }

            $message->setName($line['type']);
            $message->setDescription($line['reason']);

            $this->addLintMessage($message);
        }
    }
}
