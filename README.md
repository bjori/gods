# monGOdb Driver testS

This project aims to provide MongoDB driver creators to test their drivers against a a set
of acceptance tests.

Individual testcase is described using Extended JSON (as recognized by libbson).
A testcase expects a specifically crafted BSON document, and will return appropriate
results, noting down into its log success or failure of the incoming document from the
driver.


To initiate the testsuite call {GODS: x} where `x` is the testsuite you'd like to run
through.

Note that these tests must happen in the predefined sequence.

# Next..
Is to write the tests

# After that
Add commands to "timeout next query", fuzz BSON/messageLength etc...
