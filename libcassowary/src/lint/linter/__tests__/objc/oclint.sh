#! /bin/bash

ARCH=armv7
SYSROOT=/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneOS.platform/Developer/SDKs/iPhoneOS7.0.sdk

INCLUDES=''
for folder in `find . -type d`
do
INCLUDES="$INCLUDES -I$folder"
done

oclint -rc NUMBER_OF_PARAMETERS=10 -rc METHOD_LENGTH=180 $1 -- -x objective-c -arch $ARCH -F . -isysroot $SYSROOT -g -I$INCLUDES -c
