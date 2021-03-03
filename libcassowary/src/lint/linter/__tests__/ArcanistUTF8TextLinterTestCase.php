<?php

final class ArcanistUTF8TextLinterTestCase
    extends ArcanistExternalLinterTestCase {
    public function testLinter() {
        $this->executeTestsInDirectory(dirname(__FILE__).'/xml/');
    }
}
