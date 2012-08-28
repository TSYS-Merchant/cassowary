cassowary
=========

Cassowary is a collection of helpers and continuous integration tools for mobile development.

libcassowary
------------

Right now the `libcassowary` library (derived from [libphutil][0] / [arcanist][1]) is focused on providing a set of utilities for the [Phabricator][2] development tools. Within `libcassowary` there exists unit tests and linters that can be invoked for iOS and Android projects. All input and pull requests are welcome to make it easier to automate testing and code reviews for these mobile development environments.

To use libcassowary edit your `.arcconfig` to look something like this:

```json
{
  "project_id" : "MyProject",
  "conduit_uri" : "http://myphabricatorurl.com/",
  "phutil_libraries" : [
    "cassowary/libcassowary/src"
  ],
  "lint.engine" : "OCLintEngine",
  "unit.engine" : "OCUnitTestEngine"
}
```

You will need to clone this repository to somewhere in your project structure. iOS projects should use OCLintEngine and OCUnitTestEngine for linting and unit testing respectively. Android projects should use AndroidLintEngine and AndroidTestEngine.

iOS Linting and Unit Testing
----------------------------

`libcassowary` uses [OCLint][3] for linting Objective-C implementation files. Since OCLint is configurable and needs to know some information about the Xcode project being linted, a file `oclint.sh` must exist in any root project directory that wishes to have its files linted. A sample file is as follows:

```bash
#! /bin/bash

ARCH=armv7
SYSROOT=/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneOS.platform/Developer/SDKs/iPhoneOS5.1.sdk
CLANG_INCLUDE=/usr/lib/clang/4.0/include
PCH_PATH=src/Prefix.pch

INCLUDES=''
for folder in `find . -type d`
do
  INCLUDES="$INCLUDES -I $folder"
done

oclint -x objective-c -rc NUMBER_OF_PARAMETERS=10 -rc METHOD_LENGTH=300 -arch $ARCH -F . -isysroot=$SYSROOT -I $CLANG_INCLUDE $INCLUDES -include $PCH_PATH $1
```

It may be necessary to edit this script based on your project needs and structure. See the [command line documentation][4] for more info.

The iOS linter looks for this file and uses it as a reference point for linting individual files so make sure it exists otherwise no linting will occur.

iOS unit testing leverages the OCUnit testing framework provided within Xcode. The unit test engine expects a scheme called `UnitTests` that exists for an Xcode project. All unit tests within that scheme will be executed.

Android Linting and Unit Testing
--------------------------------

The Android developer tools provide linting and unit test capabilities out of the box. The Android lint tool will be automatically invoked for any XML or Java files if configured. For unit testing, all tests within a directory `tests` will be executed for an application. The Android unit tester does assume that a device is connected to execute the tests on.

[0]: http://github.com/facebook/libphutil
[1]: http://github.com/facebook/arcanist
[2]: http://github.com/facebook/phabricator
[3]: http://oclint.org
[4]: http://oclint.org/docs/command/