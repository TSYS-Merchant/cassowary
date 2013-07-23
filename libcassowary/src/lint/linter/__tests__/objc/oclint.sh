#! /bin/bash

ARCH=armv7
SYSROOT=/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneOS.platform/Developer/SDKs/iPhoneOS6.1.sdk

INCLUDES=''
for folder in `find . -type d`
do
  INCLUDES="$INCLUDES -I$folder"
done

oclint -rc=LONG_LINE=120 $1 -- -x objective-c -arch $ARCH -F . -isysroot $SYSROOT -g -I$INCLUDES -c
