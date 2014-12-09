
package com.imobile3.cassowary;

import java.util.Arrays;
import java.util.List;

import com.android.tools.lint.client.api.IssueRegistry;
import com.android.tools.lint.detector.api.Issue;

public class CassowaryIssueRegistry extends IssueRegistry {
    @Override
    public List<Issue> getIssues() {
        return Arrays.asList(
                BigDecimalSinDetector.ISSUE_BIG_DECIMAL_SIN,
                NamingConventionDetector.ISSUE_VARIABLE_NAMING_CONVENTION
                );
    }
}
