<?php

final class ArcanistOCLinterTestCase extends ArcanistLinterTestCase {

	/**
 	 * Tests the 120-character line limit advice
 	 */
	public function testOCLint() {
		$linter = new ArcanistOCLinter();
		$working_copy = new ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
		return this->executeTestsInDirectory(
			dirname(__FILE__) . `/objc/`,
			$linter,
			$working_copy);
	}
}