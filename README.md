Jackalope Filesystem PHPCR implementation
=========================================

This is a WIP implementation to support a filesystem implementation of PHPCR.

The implementation is meant to be lightweight and ideal for testing PHPCR
components.

Limitations
-----------

### Querying

Not supported:

- **Node type inheritance**: Currently node type inheritance is not taken into
  account - this should be fixed ASAP
- **Joins**: Will need to be implemented in a post processor
- **LOWERCASE, UPPERCASE, LENGTH operands**: Same as above
- **SQL and XPath query langauges**: Will probably never be implemented
- **Full text search**: Easy to implement if we add an additional search index
