<?php

final class ArcanistMobileGeneratedLinterTestCase extends ArcanistLinterTestCase {

    /**
     * Tests the generated file exclusions
     */
    public function testFileExclusions() {
        $linter = new ArcanistMobileGeneratedLinter();
        $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
        $this->executeTestsInDirectory(
            dirname(__FILE__) . '/generated/tests/',
            $linter,
            $working_copy);
    }
}
