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
* Extends the base filename linter to support additional characters popular
* with Objective-C projects.
*
* @group linter
*/
final class ArcanistOCFilenameLinter extends ArcanistLinter {
    const LINT_BAD_FILENAME = 1;

    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'ObjectiveCFileLint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array(
            self::LINT_BAD_FILENAME => 'Bad Filename',
        );
    }

    public function lintPath($path) {
        if (!preg_match('@^[a-z0-9~./\\\\_\@\+-]+$@i', $path)) {
            $this->raiseLintAtPath(
                self::LINT_BAD_FILENAME,
                "Name files using only letters, numbers, period, hyphen, "
                ."plus sign, apetail, tilde, and underscore.");
        }
    }
}
