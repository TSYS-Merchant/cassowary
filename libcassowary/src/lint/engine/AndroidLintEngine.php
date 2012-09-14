<?php

/*

Copyright 2011-2012 iMobile3, LLC. All rights reserved.

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
* Utilize the Android lint utilities that come with the latest ADT. The engine assumes
* that the ADT binaries are installed on the user's machine and available on the path.
*
* @group linter
*/
final class AndroidLintEngine extends ArcanistLintEngine {
    public function buildLinters() {
        $paths = $this->getPaths();
        
        $linter = new ArcanistAndroidLinter();
        
        foreach ($paths as $key => $path) {
            if (!$this->pathExists($path)) {
                unset($paths[$key]);
            }
        }
        
        foreach ($paths as $path) {
            // Only run Android linter on .java or .xml files
            if (preg_match('/\.java$/', $path) || preg_match('/\.xml$/', $path)) {
                $linter->addPath($path);
            }
        }
        
        // allow for copyright license to be enforced for projects that opt in
        $check_copyright = $this->getWorkingCopy()->getConfig('check_copyright');
        if ($check_copyright) {
        	$copyrightLinter = new ArcanistCustomLicenseLinter();
        	foreach ($paths as $path) {
				// Only run copyright linter on .h files
				if (preg_match('/\.java$/', $path)) {
					$copyrightLinter->addPath($path);
				}
			}
			
			return array(
			$linter,
			$copyrightLinter,
			);
        }
        
        return array(
        $linter,
        );
    }
}