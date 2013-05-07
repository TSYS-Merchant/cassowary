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
* Adds a custom license to source files.
*
* @group linter
*/
final class ArcanistCustomLicenseLinter extends ArcanistLinter {
    const LINT_NO_LICENSE_HEADER = 1;

    public function willLintPaths(array $paths) {
        return;
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
      return array(
        self::LINT_NO_LICENSE_HEADER   => 'No License Header',
      );
    }
  
    public function getLinterName() {
        return 'CustomLicense';
    }
  
    protected function getLicenseText($copyright_holder) {
        $year = date('Y');
        $upper = strtoupper($copyright_holder);

        return <<<EOLICENSE
/*

 Copyright {$year} {$copyright_holder}. All rights reserved.

 Redistribution and use in source and binary forms, with or without
 modification, is permitted provided that adherence to the following
 conditions is maintained. If you do not agree with these terms,
 please do not use, install, modify or redistribute this software.

 1. Redistributions of source code must retain the above copyright notice, this
 list of conditions and the following disclaimer.

 2. Redistributions in binary form must reproduce the above copyright notice,
 this list of conditions and the following disclaimer in the documentation
 and/or other materials provided with the distribution.

 THIS SOFTWARE IS PROVIDED BY {$upper} "AS IS" AND ANY EXPRESS OR
 IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
 EVENT SHALL {$upper} OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

 */


EOLICENSE;
    }
    
    protected function getLicensePatterns() {
        $maybe_script = '(#![^~\R~u]+?[~\R~u])?';
        return array(
          "@^{$maybe_script}//[^~\R~u]*Copyright[^~\R~u]*[~\R~u]\s*@i",
          "@^{$maybe_script}/[*](?:[^*]|[*][^/])*?Copyright.*?[*]/\s*@is",
          "@^{$maybe_script}\s*@",
        );
    }
    
    public function lintPath($path) {
        $copyright_holder = $this->getConfig('copyright_holder');
        if ($copyright_holder === null) {
            $working_copy = $this->getEngine()->getWorkingCopy();
            $copyright_holder = $working_copy->getConfig('copyright_holder');
        }

        if (!$copyright_holder) {
            return;
        }

        $patterns = $this->getLicensePatterns();
        $license = $this->getLicenseText($copyright_holder);

        $data = $this->getData($path);
        $matches = 0;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $data, $matches)) {
                $expect = rtrim(implode('', array_slice($matches, 1)))."\r\n".$license;
                if (trim($matches[0]) != trim($expect)) {
                    $this->raiseLintAtOffset(
                        0,
                        self::LINT_NO_LICENSE_HEADER,
                        'This file has a missing or out of date license header.',
                        $matches[0],
                        ltrim($expect));
                }
                break;
            }
        }
    }
}