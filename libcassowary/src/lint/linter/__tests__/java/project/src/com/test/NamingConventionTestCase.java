
package com.test;

public class NamingConventionTestCase {
    public static final int notAConstant = 1;
    public static final int ACTUALLY_A_CONSTANT = 2;

    private static int sStatic;
    private static int mStatic;

    private int mPrivate;
    private int mPrivate2, private3;
    protected int mProtected;
    public int publicVar;

    public void doSomething() {
        int var = 0;
    }

    private class Inner {
        private int mPrivate;
        protected int protectedVar;
        public int publicVar;
    }
}

class NamingConventionTestCase2 {
    private int mPrivate;
    private int pPrivate;

    class Inner {
        private int mPrivate;
        public int publicVar;
    }
}
