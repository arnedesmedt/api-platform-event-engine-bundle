# api-platform-event-engine-bundle
A library that creates a bridge between API Platform and Event Engine.

The configuration is not that generic. So feel free to fork this repository and make it generic or change things for your own needs.

## Setup aggregagates

- Aggregate needs to implement the AggregateRoot interface
- State needs to implement the ChangeApiResource (ChangeApiResourceByNamespace trait can be used), JsonSchemaAwareRecord (JsonSchemaAwareRecordLogic trait can be used).
- Command needs to implement JsonSchemaAwareRecord (JsonSchemaAwareRecordLogic trait can be used), AggregateCommand (CommandNameAsAggregateMethod trait can be used).
 
