![cassowary](https://raw.github.com/imobile3/cassowary/master/cassowary.png)

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
  "lint.engine" : "MobileLintEngine",
  "unit.engine" : "MobileUnitTestEngine"
}
```

You will need to clone this repository to somewhere in your project structure. iOS projects should use OCLintEngine and OCUnitTestEngine for linting and unit testing respectively. Android projects should use AndroidLintEngine and AndroidTestEngine. .NET projects should use DotNetLintEngine. If you need support for several platforms in a hybrid repository, use MobileLintEngine and MobileUnitTestEngine.

iOS Linting and Unit Testing
----------------------------

`libcassowary` uses [OCLint][3] for linting Objective-C implementation files. Since OCLint is configurable and needs to know some information about the Xcode project being linted, a file `oclint.sh` must exist in any root project directory that wishes to have its files linted. A sample file is as follows:

```bash
#! /bin/bash

ARCH=armv7
SYSROOT=/Applications/Xcode.app/Contents/Developer/Platforms/iPhoneOS.platform/Developer/SDKs/iPhoneOS.sdk
PCH_PATH=src/Prefix.pch

INCLUDES=''
for folder in `find . -type d`
do
  INCLUDES="$INCLUDES -I$folder"
done

oclint $1 -- -x objective-c -arch $ARCH -F . -isysroot $SYSROOT -g -I$INCLUDES -include $PCH_PATH -c
```

It may be necessary to edit this script based on your project needs and structure. See the [command line documentation][4] for more info.

The iOS linter looks for this file and uses it as a reference point for linting individual files so make sure it exists otherwise no linting will occur.

iOS unit testing leverages the OCUnit testing framework provided within Xcode and is built and executed using [xctool][5]. The unit test engine expects to find a file named `.xctool-args` that exists parallel to an Xcode project. All unit tests per that configuration will be executed; see the `xctool` documentation for how to configure its execution using this file. To install `xctool` just run:

```bash
brew install xctool
```

Android Linting and Unit Testing
--------------------------------

The Android developer tools provide linting and unit test capabilities out of the box. The Android lint tool will be automatically invoked for any XML or Java files if configured. For unit testing, all tests within a directory `tests` will be executed for an application. The Android unit tester does assume that a device is connected to execute the tests on.

.NET Linting and Unit Testing
-----------------------------

`libcassowary` uses the [ReSharper Command Line Tools][6] for linting. To install them just run:

```bash
choco install resharper-clt
```

The .NET linter invokes the full ReSharper suite and can utilize any local configuration you have in place in your solution(s).

.NET unit testing supports both classic .NET and .NET Core.

License / Support
=================

Copyright 2012-2019 iMobile3, LLC. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that adherence to the following
conditions is maintained. If you do not agree with these terms,
please do not use, install, modify or redistribute this software.

1. Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY IMOBILE3, LLC "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL IMOBILE3, LLC OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

All contributions are welcome to improve the tools for more efficient, quality mobile development.

[0]: http://github.com/facebook/libphutil
[1]: http://github.com/facebook/arcanist
[2]: http://github.com/facebook/phabricator
[3]: http://oclint.org
[4]: http://docs.oclint.org/en/dev/usage/oclint.html
[5]: http://github.com/facebook/xctool
[6]: https://www.jetbrains.com/resharper/features/command-line.html
