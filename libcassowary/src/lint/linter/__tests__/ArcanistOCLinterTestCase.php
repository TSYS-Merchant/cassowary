<?php

final class ArcanistOCLinterTestCase extends ArcanistLinterTestCase {

    /**
     * Tests the 120-character line limit advice
     */
    public function testLinter() {
        $linter = new ArcanistOCLinter();
        $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
        return $this->executeTestsInDirectory(
            dirname(__FILE__) . '/objc/tests/',
            $linter,
            $working_copy);
    }
}
