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
* OCUnit wrapper
*
* To use, set unit_engine in .arcconfig, or use --engine flag
* with arc unit.
*
* @group unitrun
*/
final class OCUnitTestEngine extends ArcanistBaseUnitTestEngine {
    private $configFile;
    private $affectedTests;
    
    public function run() {
        $resultArray = array();
        $testPaths = array();
        
        /* Looking for project root directory */
        foreach ($this->getPaths() as $path) {
            $rootPath = $path;
            
            /* Checking all levels of path */
            do {
                /* We only want projects that have UnitTests */
                /* Only add path once per project */
                if (file_exists($rootPath."/UnitTests") && !in_array($rootPath, $testPaths)) {
                    array_push($testPaths, $rootPath);
                }
                
                /* Stripping last level */
                $last = strrchr($rootPath, "/");
                $rootPath = str_replace($last, "", $rootPath);
            } while ($last);
        }
        
        /* Checking to see if no paths were added */
        if (count($testPaths) == 0) {
            throw new ArcanistNoEffectException("No tests to run.");
        }
        
        /* Trying to build for every project */
        foreach ($testPaths as $path) {
            chdir($path);
            exec("xcodebuild -target UnitTests -sdk iphonesimulator TEST_AFTER_BUILD=YES ARCHS=i386 clean build", $testOutput, $_);
            
            $testResult = $this->parseOutput($testOutput);
            $resultArray = array_merge($resultArray, $testResult);
        }
        
        return $resultArray;
    }
    
    private function parseOutput($testOutput) {
        $state = 0;
        $userData = null;
        $result = null;
        $resultArray = array();
        
        foreach($testOutput as $line) {
            if($c = preg_match_all("/.*?(\\[.*?\\]).*?((?:[a-z][a-z]+)).*?([+-]?\\d*\\.\\d+)(?![-+0-9\\.])/is", $line, $matches) && $matches[2][0] === 'passed') {
                $result = new ArcanistUnitTestResult();
                $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
                $result->setName($matches[1][0]);
                $result->setDuration($matches[3][0]);
                array_push($resultArray, $result);
            } else if($c = preg_match_all("/((?:\\/[\\w\\.\\-]+)+):.*?(\\d+):.*?((?:[a-z][a-z]+)):.*?(\\[.*?\\]).*? :  *?(.*)/is", $line, $matches) && $matches[3][0] === 'error') {
                $result = new ArcanistUnitTestResult();
                $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
                $result->setName($matches[4][0]);
                $result->setUserData($matches[5][0]);
                array_push($resultArray, $result);
            }
        }
        
        return $resultArray;
    }
}