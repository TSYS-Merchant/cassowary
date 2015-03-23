<?php

final class ArcanistAndroidLinterTestCase extends ArcanistLinterTestCase {

    /**
     * Tests the custom Android linters
     */
    public function testLinter() {
        $linter = new ArcanistAndroidLinter(null);
        $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
        $this->executeTestsInDirectory(
            dirname(__FILE__) . '/java/tests/',
            $linter,
            $working_copy);
    }
}
