<?php

final class ArcanistAndroidLinterTestCase extends ArcanistLinterTestCase {

    /**
     * Tests the Android naming convention linter
     */
    public function testNamingConvention() {
        $linter = new ArcanistAndroidLinter(null);
        $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
        $this->executeTestsInDirectory(
            dirname(__FILE__) . '/java/tests/',
            $linter,
            $working_copy);
    }
}
