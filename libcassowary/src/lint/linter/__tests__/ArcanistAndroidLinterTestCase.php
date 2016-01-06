<?php

final class ArcanistAndroidLinterTestCase
    extends ArcanistExternalLinterTestCase {
    public function testLinter() {
        $this->executeTestsInDirectory(dirname(__FILE__).'/java/');
    }
}
