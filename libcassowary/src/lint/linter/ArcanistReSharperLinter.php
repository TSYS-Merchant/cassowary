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
 * Uses JetBrains' ReSharper Command Line Tools to detect various errors in
 * .NET code. To use this linter, you must install the above tools and have
 * inspectcode.exe on your PATH.
 *
 * @group linter
 */
final class ArcanistReSharperLinter extends ArcanistLinter {

    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'ReSharperLint';
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    public function lintPath($path) {
        $lint_output = tempnam(sys_get_temp_dir(), 'arclint.xml');
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        try {
            exec("inspectcode {$path_on_disk} /o={$lint_output}");
        } catch (CommandException $e) {
            return;
        }

        $filexml = simplexml_load_file($lint_output);

        if ($filexml->attributes()->ToolsVersion < 8.0) {
            throw new ArcanistUsageException("Unsupported Command Line Tools "
            ."output version. Please update to the latest version.");
        } else if ($filexml->attributes()->ToolsVersion > 8.0) {
            throw new ArcanistUsageException("Unsupported Command Line Tools "
            ."output version. Cassowary needs an update to match.");
        }

        $severity_map = array();
        $name_map = array();
        foreach ($filexml->xpath('//IssueType') as $issue_type) {
            if ($issue_type->attributes()->Severity == 'ERROR') {
                $severity_map[(string)$issue_type->attributes()->Id] =
                    ArcanistLintSeverity::SEVERITY_ERROR;
            } else if ($issue_type->attributes()->Severity == 'WARNING') {
                $severity_map[(string)$issue_type->attributes()->Id] =
                    ArcanistLintSeverity::SEVERITY_WARNING;
            } else {
                $severity_map[(string)$issue_type->attributes()->Id] =
                    ArcanistLintSeverity::SEVERITY_ADVICE;
            }

            $name_map[(string)$issue_type->attributes()->Id] =
                $issue_type->attributes()->Description;
        }

        foreach ($filexml->xpath('//Issue') as $issue) {
            $message = new ArcanistLintMessage();
            $message->setPath(
                Filesystem::resolvePath((string)$issue->attributes()->File,
                    dirname($path_on_disk)));

            $message_line = intval($issue->attributes()->Line);
            if ($message_line > 0) {
                $message->setLine($message_line);
            }

            $message->setName(
                (string)$name_map[(string)$issue->attributes()->TypeId]);
            $message->setCode((string)$issue->attributes()->TypeId);
            $message->setDescription((string)$issue->attributes()->Message);
            $message->setSeverity(
                $severity_map[(string)$issue->attributes()->TypeId]);

            // Skip line number check, since we're linting the whole project
            $message->setBypassChangedLineFiltering(true);

            $this->addLintMessage($message);
        }

        unlink($lint_output);
    }
}
