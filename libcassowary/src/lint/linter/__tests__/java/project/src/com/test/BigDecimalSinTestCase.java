
package com.test;

import java.math.BigDecimal;

public class BigDecimalSinTestCase {
    public void doSomething() {
        BigDecimal bd1 = new BigDecimal(12);
        BigDecimal bd2 = new BigDecimal("1.2");
        BigDecimal bd3 = new BigDecimal(1.2);

        bd1.intValue();
        bd3.floatValue();
        bd3.doubleValue();
    }
}
