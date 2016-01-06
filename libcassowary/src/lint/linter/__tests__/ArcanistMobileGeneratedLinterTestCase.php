<?php

final class ArcanistMobileGeneratedLinterTestCase
    extends ArcanistExternalLinterTestCase {
    public function testLinter() {
        $this->executeTestsInDirectory(dirname(__FILE__).'/generated/');
    }
}
