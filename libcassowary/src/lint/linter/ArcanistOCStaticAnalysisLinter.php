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
 * Uses Clang's static analysis tools to find warnings such as memory leaks.
 *
 * @group linter
 */
final class ArcanistOCStaticAnalysisLinter extends ArcanistLinter {
    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'ObjectiveCStaticAnalysisLint';
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
        chdir($path);

        $stdout = array();
        $_ = 0;
        exec(phutil_get_library_root("libcassowary") .
        'xctool -reporter json-stream clean build RUN_CLANG_STATIC_ANALYZER=YES',
            $stdout, $_);
        foreach ($stdout as $line) {
            $resultItem = json_decode($line, true);

            $matches = array();
            if (isset($resultItem['emittedOutputText']) && $c =
                            preg_match_all("/((?:\\/[\\w\\.\\-]+)+):(\\d+):(\\d+): ((?:[a-z][a-z]+)): (\w+(\s+\w+)*)/is",
                                           $resultItem['emittedOutputText'])
            ) {
                $errors = explode("\n", $resultItem['emittedOutputText']);

                foreach ($errors as $error) {
                    if ($c = preg_match_all('/((?:\\/[\\w\\.\\-]+)+):(\\d+):(\\d+): ((?:[a-z][a-z]+)): (.*)/is',
                                            $error, $matches)) {
                        $message = new ArcanistLintMessage();
                        $message->setPath($matches[1][0]);
                        $message->setLine($matches[2][0]);
                        $message->setChar($matches[3][0]);
                        $message->setCode('CLANG');

                        if ($matches[4][0] === 'error') {
                            $message->setSeverity(
                                                  ArcanistLintSeverity::SEVERITY_ERROR);
                        } else if ($matches[4][0] === 'warning') {
                            $message->setSeverity(
                                                  ArcanistLintSeverity::SEVERITY_WARNING);
                        } else {
                            $message->setSeverity(
                                                  ArcanistLintSeverity::SEVERITY_ADVICE);
                        }

                        $message->setName($matches[5][0]);
                        $message->setDescription($matches[5][0]);

                        $this->addLintMessage($message);
                    }
                }
            }
        }
    }
}
