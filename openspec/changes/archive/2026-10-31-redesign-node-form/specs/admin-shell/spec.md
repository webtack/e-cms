## ADDED Requirements

### Requirement: Липка панель дій форм
Each create and edit form in the admin (a content type, a field of a type, a record) SHALL have an action bar that stays pinned to the bottom edge of the window when scrolling, on all screen sizes. The bar SHALL contain the button «Зберегти» and the link «Скасувати», which returns to the list without changes; the buttons SHALL NOT stand in the flow of the form. The bar SHALL have an opaque background and a top border, take into account the safe bottom area of the device, and not overlap the last fields of the form (the end of the form can be scrolled above the bar). Between the content of the form and the bar there SHALL be a small indent, so that the form does not stick to the bar. During submission the button «Зберегти» SHALL be disabled.

#### Scenario: Long form
- **WHEN** the user opens a form with long content and does not scroll it to the end
- **THEN** the buttons «Скасувати» and «Зберегти» are visible at the bottom of the window

#### Scenario: Cancellation
- **WHEN** the user presses «Скасувати» in the bar
- **THEN** they return to the list without saved changes

#### Scenario: Saving from the bar
- **WHEN** the user presses «Зберегти» in the bar
- **THEN** the form is submitted as before

#### Scenario: All admin forms
- **WHEN** the user opens the creation form of a type, of a field or of a record
- **THEN** each has a sticky action bar

#### Scenario: The end of the form is not overlapped
- **WHEN** the user scrolls the form to the end
- **THEN** the last field is fully visible above the bar

#### Scenario: Indent from the bar
- **WHEN** the user scrolls the form to the end
- **THEN** there is a visible gap between the last element of the form and the action bar

### Requirement: Рамка форм типу й поля
The content of the create and edit forms of a content type and of a field of a type SHALL be in a container with a rounded frame (`rounded-md border`). The action bar SHALL stay outside the frame, full width.

#### Scenario: Form of a type in a frame
- **WHEN** the user opens the creation or edit form of a content type
- **THEN** the fields of the form are surrounded by a rounded frame, and the action bar is outside it

#### Scenario: Form of a field in a frame
- **WHEN** the user opens the creation or edit form of a field of a type
- **THEN** the fields of the form are surrounded by a rounded frame, and the action bar is outside it
