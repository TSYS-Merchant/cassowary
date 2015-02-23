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
 * Uses JetBrains' ReSharper Command Line Tools to detect various errors in
 * .NET code. To use this linter, you must install the above tools and have
 * inspectcode.exe on your PATH.
 *
 * @group linter
 */
final class ArcanistReSharperLinter extends ArcanistLinter {
    private $allLintResults;

    public function willLintPaths(array $paths) {
        $this->allLintResults = array();
        $analysis_paths = array();
        $filesystem_paths = array();

        foreach ($paths as $key => $path) {
            $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
            $current_directory = dirname($path_on_disk);
            $filesystem_paths[] = Filesystem::resolvePath($path_on_disk,
                $current_directory);
            $analysis_path = null;

            do {
                if ($current_directory === '/'
                        || $current_directory === 'C:\\'
                ) {
                    break;
                }

                foreach (new DirectoryIterator($current_directory) as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }

                    // if a .sln file can be found we know
                    // we're in the correct place
                    if ($file->getExtension() == 'sln') {
                        $analysis_path = $file->getPathname();
                    }
                }

                $current_directory = dirname($current_directory);
            } while (empty($analysis_path));

            if ($analysis_path != null
                    && !in_array($analysis_path, $analysis_paths)
            ) {
                $analysis_paths[] = $analysis_path;
            }
        }

        foreach ($analysis_paths as $key => $path) {
            $lint_output = tempnam(sys_get_temp_dir(), 'arclint.xml');
            $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

            execx('inspectcode %s /o=%s', $path_on_disk, $lint_output);

            $filexml = simplexml_load_string(file_get_contents($lint_output));
            if (empty($filexml)) {
                throw new ArcanistUsageException("Unsupported Command Line Tools "
                . "output version. Please update to the latest version.");
            }

            if ($filexml->attributes()->ToolsVersion < 8.1) {
                throw new ArcanistUsageException("Unsupported Command Line Tools "
                . "output version. Please update to the latest version.");
            } else if ($filexml->attributes()->ToolsVersion > 8.1) {
                throw new ArcanistUsageException("Unsupported Command Line Tools "
                . "output version. Cassowary needs an update to match.");
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
                $linted_file = Filesystem::resolvePath(
                    (string)$issue->attributes()->File,
                    dirname($path_on_disk));

                if (empty($linted_file) || !in_array($linted_file,
                        $filesystem_paths)) {
                    continue;
                }

                $message = new ArcanistLintMessage();
                $message->setPath($linted_file);

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
                $message->setBypassChangedLineFiltering(true);

                if (!array_key_exists($linted_file, $this->allLintResults)) {
                    $this->allLintResults[$linted_file] = array();
                }

                $this->allLintResults[$linted_file][] = $message;
            }

            unlink($lint_output);
        }
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
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        foreach ($this->allLintResults[$path_on_disk] as $key => $message) {
            $this->addLintMessage($message);
        }
    }
}
