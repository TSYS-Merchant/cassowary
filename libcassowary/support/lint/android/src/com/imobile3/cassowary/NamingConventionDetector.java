
package com.imobile3.cassowary;

import java.io.File;
import java.util.ArrayList;
import java.util.List;

import lombok.ast.AstVisitor;
import lombok.ast.ClassDeclaration;
import lombok.ast.ForwardingAstVisitor;
import lombok.ast.MethodInvocation;
import lombok.ast.Modifiers;
import lombok.ast.Node;
import lombok.ast.StrictListAccessor;
import lombok.ast.VariableDeclaration;
import lombok.ast.VariableDefinitionEntry;

import com.android.annotations.Nullable;
import com.android.tools.lint.detector.api.Category;
import com.android.tools.lint.detector.api.Context;
import com.android.tools.lint.detector.api.Detector;
import com.android.tools.lint.detector.api.Implementation;
import com.android.tools.lint.detector.api.Issue;
import com.android.tools.lint.detector.api.JavaContext;
import com.android.tools.lint.detector.api.Scope;
import com.android.tools.lint.detector.api.Severity;

public class NamingConventionDetector extends Detector implements
        Detector.JavaScanner {
    public static final Issue ISSUE_VARIABLE_NAMING_CONVENTION = Issue.create(
            "NamingConvention",
            "Variable naming convention not used",
            "Non-public, non-static field names start with \"m\". " +
                    "Static field names start with \"s\". " +
                    "Other fields start with a lower case letter. " +
                    "Static final fields (constants) are " +
                    "ALL_CAPS_WITH_UNDERSCORES.",
            "",
            Category.CORRECTNESS, 5, Severity.WARNING,
            new Implementation(NamingConventionDetector.class,
                    Scope.JAVA_FILE_SCOPE));

    public NamingConventionDetector() {
    }

    @Override
    public boolean appliesToResourceRefs() {
        return false;
    }

    @Override
    public boolean appliesTo(Context context, File file) {
        return true;
    }

    @Override
    @Nullable
    public AstVisitor createJavaVisitor(JavaContext context) {
        return new NamingConventionVisitor(context);
    }

    @Override
    public List<String> getApplicableMethodNames() {
        return null;
    }

    @Override
    public List<Class<? extends Node>> getApplicableNodeTypes() {
        List<Class<? extends Node>> types =
                new ArrayList<Class<? extends Node>>(1);
        types.add(lombok.ast.VariableDeclaration.class);
        return types;
    }

    @Override
    public void visitMethod(JavaContext context, AstVisitor visitor,
            MethodInvocation methodInvocation) {
    }

    @Override
    public void visitResourceReference(JavaContext context, AstVisitor visitor,
            Node node, String type, String name, boolean isFramework) {
    }

    private class NamingConventionVisitor extends ForwardingAstVisitor {
        private final JavaContext mContext;

        public NamingConventionVisitor(JavaContext context) {
            mContext = context;
        }

        @Override
        public boolean visitVariableDeclaration(VariableDeclaration node) {
            ClassDeclaration cd = getClassDeclaration(node);

            ClassDeclaration parent = cd;
            while (parent != null) {
                if (parent.astName().toString().equals("R")) {
                    return false;
                }
                parent = getClassDeclaration(parent.getParent());
            }

            Modifiers modifiers = node.astDefinition().astModifiers();

            StrictListAccessor<VariableDefinitionEntry, VariableDeclaration> varDefinitions =
                    node.getVariableDefinitionEntries();
            for (VariableDefinitionEntry varDefinition : varDefinitions) {
                String name = varDefinition.astName().astValue();

                if (name.equals("serialVersionUID")) {
                    continue;
                }

                if (modifiers.isStatic() && modifiers.isFinal()) {
                    // CONSTANT
                    if (name.matches(".*[a-z].*")) {
                        mContext.report(
                                ISSUE_VARIABLE_NAMING_CONVENTION,
                                node,
                                mContext.getLocation(node),
                                "Constants should be named in all " +
                                        "uppercase with underscores.",
                                name);
                    }
                } else {
                    if (name.contains("_")) {
                        mContext.report(
                                ISSUE_VARIABLE_NAMING_CONVENTION,
                                node,
                                mContext.getLocation(node),
                                "Underscores are not allowed in non-" +
                                        "constant variable names. " +
                                        "Use camelCase.",
                                name);
                    } else if (modifiers.isStatic()) { // sStatic
                        if (!name.matches("^s[A-Z].*")) {
                            mContext.report(
                                    ISSUE_VARIABLE_NAMING_CONVENTION,
                                    node,
                                    mContext.getLocation(node),
                                    "Static variable names should " +
                                            "begin with \"s\".",
                                    name);
                        }
                    } else if (modifiers.isProtected()
                            || modifiers.isPrivate()) { // mMember
                        if (!name.matches("^m[A-Z].*")) {
                            mContext.report(
                                    ISSUE_VARIABLE_NAMING_CONVENTION,
                                    node,
                                    mContext.getLocation(node),
                                    "Member variable names should " +
                                            "begin with \"m\".",
                                    name);
                        }
                    } else { // camelCase
                        if (name.matches("^[A-Z].*")) {
                            mContext.report(
                                    ISSUE_VARIABLE_NAMING_CONVENTION,
                                    node,
                                    mContext.getLocation(node),
                                    "Variable names should begin " +
                                            "with a lower-case letter.",
                                    name);
                        } else if (name.matches("^[msp][A-Z].*")) {
                            mContext.report(
                                    ISSUE_VARIABLE_NAMING_CONVENTION,
                                    node,
                                    mContext.getLocation(node),
                                    "Non-member variables should not be " +
                                            "prefixed.",
                                    name);
                        }
                    }
                }
            }

            return false;
        }
    }

    private ClassDeclaration getClassDeclaration(Node node) {
        while (node != null) {
            Class<? extends Node> type = node.getClass();
            if (type == ClassDeclaration.class) {
                return (ClassDeclaration)node;
            }
            node = node.getParent();
        }

        return null;
    }
}
