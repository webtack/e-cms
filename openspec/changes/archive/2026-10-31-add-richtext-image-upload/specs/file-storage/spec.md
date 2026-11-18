# Spec Delta

## ADDED Requirements

### Requirement: Завантаження для поля форматованого тексту
An upload request by field (the content type and the field key) SHALL also accept a field of the type `richtext`: the profile SHALL be `image`, and the rules SHALL be the rules of the whole profile (the field `richtext` has no file settings). For a field that holds no files and is not `richtext`, the request SHALL be rejected with an error for the field.

#### Scenario: Upload by the field richtext
- **WHEN** the administrator sends a PNG and a field of the type `richtext`
- **THEN** the file is accepted, the record profile is `image`, and the response contains a relative address of the form `/storage/{uuid}.png`

#### Scenario: Profile is not `image`
- **WHEN** the administrator sends a PDF and a field of the type `richtext`
- **THEN** the file is rejected by the rules of the profile `image`

#### Scenario: Field without files
- **WHEN** the administrator sends a file and a field of the type `string`
- **THEN** the request is rejected with an error for the field
