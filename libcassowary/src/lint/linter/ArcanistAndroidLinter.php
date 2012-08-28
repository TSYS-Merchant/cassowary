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
* Uses Android Lint to detect various errors in Java code. To use this linter,
* you must install the Android SDK and configure which codes you want to be
* reported as errors, warnings and advice.
*
* @group linter
*/
final class ArcanistAndroidLinter extends ArcanistLinter {
    var $arc_lint_location = '';
    
    private function getLintPath() {
        $lint_bin = "lint";
        $this->arc_lint_location = tempnam(sys_get_temp_dir(), 'arclint.xml');
        
        list($err) = exec_manual('which %s', $lint_bin);
        if ($err) {
            throw new ArcanistUsageException("Lint does not appear to be available on the path. Make sure that the Android tools directory is part of your path.");
        }
        
        return $lint_bin;
    }
    
    public function willLintPaths(array $paths) {
        return;
    }
    
    public function getLinterName() {
        return 'AndroidLint';
    }
    
    public function getLintSeverityMap() {
        return array();
    }
    
    public function getLintNameMap() {
        return array();
    }
    
    public function lintPath($path) {
        $lint_bin = $this->getLintPath();
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
        
        try {
            exec("{$lint_bin} --showall --nolines --quiet --xml {$this->arc_lint_location} {$path_on_disk}");
        }
        catch(CommandException $e) {
            return;
        }
        
        $filexml = simplexml_load_file($this->arc_lint_location);
        
        $messages = array();
        foreach ($filexml as $issue) {
            $loc_attrs = $issue->location->attributes();
            $issue_attrs = $issue->attributes();
            
            $message = new ArcanistLintMessage();
            $message->setPath($loc_attrs->file);
            $message->setLine(intval($loc_attrs->line));
            $message->setChar(intval($loc_attrs->column));
            $message->setName($issue_attrs->id);
            
            // Parsing Code stored in AndroidLint Message
            $code = strtok($issue_attrs->message, " ");
            $code = str_replace(array('[', ']'), "", $code);
            $message->setCode($code);
            
            // Removing Code from AndroidLint Message
            $android_message = preg_replace("/\[.*?\]/", "", $issue_attrs->message);
            $message->setDescription($android_message);
            
            // Setting Severity
            if(strcmp($issue_attrs->severity,"Error") == 0)
            {
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            } else if(strcmp($issue_attrs->severity,"Warning") == 0)
            {
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            }
            
            $this->addLintMessage($message);
        }
        
        try {
            exec("rm {$this->arc_lint_location}");
        }
        catch(CommandException $e) {
            return;
        }
    }
}