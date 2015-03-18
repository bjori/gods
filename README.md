# monGOdb Driver testS

This project aims to provide MongoDB driver creators to test their drivers against a set
of acceptance tests.

Individual testcase is described using Extended JSON (as recognized by libbson).
A testcase expects a specifically crafted BSON document, and will return appropriate
results, noting down into its log success or failure of the incoming document from the
driver.


To initiate the testsuite call {GODS: x} where `x` is the testsuite you'd like to run
through.

Note that these tests must happen in the predefined sequence.

# Whats the difference?
GODS is not a test runner, it is the testbed - and it will decide on if you passed the test or not.
This means that GODS will tell you if you succeeded, not your test runner.
Attempting to skip a test, or incorrectly marking test a passed is therefore not possible.

This makes GODS ideal for interopability and ACID style testing.

# Current very verbose output

 - https://gist.github.com/bjori/e6a8747a009234d41f06

# Next..
Is to write the tests

# After that
Add commands to "timeout next query", fuzz BSON/messageLength etc...
