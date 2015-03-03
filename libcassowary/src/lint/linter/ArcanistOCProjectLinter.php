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
* Lints .pbxproj files to make sure the code signing identity
* is not set to a specific developer.
*
* @group linter
*/
final class ArcanistOCProjectLinter extends ArcanistLinter {
    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'ObjectiveCProjectLint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    public function lintPath($path) {
        $this->checkPathForCodeSigning($path);
        $this->checkPathForDeploymentTarget($path);
        $this->checkPathForValidArchitectures($path);
    }

    public function checkPathForCodeSigning($path) {
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        try {
            $stdout = array();
            $_ = 0;
            exec("grep -n -b \"iPhone Developer:\" $path_on_disk", $stdout, $_);
            } catch (CommandException $e) {
            $stdout = $e->getStdout();
        }

        foreach ($stdout as $line) {
            $matches = array();
            if ($c = preg_match_all('/(\d*):(\d*):(.*)/i',
                $line, $matches)) {
                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setLine($matches[1][0]);
                $message->setChar($matches[2][0]);
                $message->setCode('Code Signing');
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);

                $message->setName('Hardcoded Signing Identity');
                $message->setDescription('Code signing identity is set to a specific developer instead of the generic "iPhone Developer" setting.');

                $this->addLintMessage($message);
            }
        }
    }

    public function checkPathForDeploymentTarget($path) {
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        try {
            $stdout = array();
            $_ = 0;
            exec("grep -n -b \"IPHONEOS_DEPLOYMENT_TARGET\" $path_on_disk", $stdout, $_);
            } catch (CommandException $e) {
            $stdout = $e->getStdout();
        }

        foreach ($stdout as $line) {
            $matches = array();
            if ($c = preg_match_all('/(\d*):(\d*):(.*)/i',
                $line, $matches)) {

                $components = explode(' = ', $matches[3][0]);

                if (intval($components[1]) < 7) {
                    $message = new ArcanistLintMessage();
                    $message->setPath($path);
                    $message->setLine($matches[1][0]);
                    $message->setChar($matches[2][0]);
                    $message->setCode('App Store');
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

                    $message->setName('Deployment Target Too Low');
                    $message->setDescription('Deployment target must be a minimum of 7.0 or the app will be rejected by Apple.');

                    $this->addLintMessage($message);
                }
            }
        }
    }

    public function checkPathForValidArchitectures($path) {
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        try {
            $stdout = array();
            $_ = 0;
            exec("grep -n -b \"_*ARCHS\" $path_on_disk", $stdout, $_);
            } catch (CommandException $e) {
            $stdout = $e->getStdout();
        }

        foreach ($stdout as $line) {
            $matches = array();
            if ($c = preg_match_all('/(\d*):(\d*):(.*)/i',
                $line, $matches)) {

                $components = explode(' = ', $matches[3][0]);
                if (substr_count($components[1], 'arm64') <= 0) {
                    $message = new ArcanistLintMessage();
                    $message->setPath($path);
                    $message->setLine($matches[1][0]);
                    $message->setChar($matches[2][0]);
                    $message->setCode('App Store');
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

                    $message->setName('ARM64 Support Required');
                    $message->setDescription('Default architecture settings have been overridden without arm64 support. This app will be rejected by Apple.');

                    $this->addLintMessage($message);
                }
            }
        }
    }
}
