
package com.imobile3.cassowary;

import java.util.Arrays;
import java.util.Collections;
import java.util.List;

import org.objectweb.asm.tree.ClassNode;
import org.objectweb.asm.tree.MethodInsnNode;
import org.objectweb.asm.tree.MethodNode;

import com.android.SdkConstants;
import com.android.annotations.Nullable;
import com.android.tools.lint.detector.api.Category;
import com.android.tools.lint.detector.api.ClassContext;
import com.android.tools.lint.detector.api.Detector;
import com.android.tools.lint.detector.api.Implementation;
import com.android.tools.lint.detector.api.Issue;
import com.android.tools.lint.detector.api.Scope;
import com.android.tools.lint.detector.api.Severity;

public class BigDecimalSinDetector extends Detector
        implements Detector.ClassScanner {
    public static final Issue ISSUE_BIG_DECIMAL_SIN = Issue.create(
            "BigDecimalSin",
            "Improper usage of BigDecimal",
            "This is here to protect you from yourself.",
            "",
            Category.CORRECTNESS, 10, Severity.WARNING,
            new Implementation(BigDecimalSinDetector.class,
                    Scope.CLASS_FILE_SCOPE));

    private static final String BIG_DECIMAL_OWNER = "java/math/BigDecimal";
    private static final String METHOD_DOUBLE_VALUE = "doubleValue";
    private static final String METHOD_FLOAT_VALUE = "floatValue";

    public BigDecimalSinDetector() {
    }

    @Override
    @Nullable
    public List<String> getApplicableCallNames() {
        return Arrays.asList(
                SdkConstants.CONSTRUCTOR_NAME
                );
    }

    @Override
    @Nullable
    public List<String> getApplicableCallOwners() {
        return Collections.singletonList(BIG_DECIMAL_OWNER);
    }

    @Override
    public void checkCall(ClassContext context, ClassNode classNode, MethodNode method,
            MethodInsnNode call) {
        if (BIG_DECIMAL_OWNER.equals(call.owner)) {
            if (SdkConstants.CONSTRUCTOR_NAME.equals(call.name)) {
                if ("(D)V".equals(call.desc)) {
                    context.report(
                            ISSUE_BIG_DECIMAL_SIN,
                            method,
                            call,
                            context.getLocation(call),
                            "Passing floating-point numbers to new BigDecimal() is unsafe. Use BigDecimal.valueOf() instead.",
                            null);
                }
            } else if (METHOD_DOUBLE_VALUE.equals(call.name)
                    || METHOD_FLOAT_VALUE.equals(call.name)) {
                context.report(
                        ISSUE_BIG_DECIMAL_SIN,
                        method,
                        call,
                        context.getLocation(call),
                        "You probably don't need to convert a BigDecimal to a floating-point number. If you are trying to format it for display, NumberFormat will format BigDecimal directly.",
                        null);
            }
        }
    }
}
