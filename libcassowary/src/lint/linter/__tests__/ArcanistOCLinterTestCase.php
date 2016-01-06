<?php

final class ArcanistOCLinterTestCase
    extends ArcanistExternalLinterTestCase {
    public function testLinter() {
        $this->executeTestsInDirectory(dirname(__FILE__).'/objc/');
    }
}
