# Spec Delta

## MODIFIED Requirements

### Requirement: Notifications about the result of actions
The shell SHALL show toast notifications for the flash messages `success` and `error` passed by the server after a user action. Every new flash message SHALL produce a toast, including a message whose text equals the previous one. Toasts SHALL appear in the top-right corner of the viewport and MAY overlap the header. Each toast SHALL be visually distinguished by its status (success, error, warning, info) with a status color in both the light and the dark theme, SHALL be sized for easy reading, and SHALL have a close button. A success toast SHALL stay visible for 4 seconds and an error toast for 8 seconds unless the user closes it earlier.

#### Scenario: Success message
- **WHEN** the server returns the next page with the flash message `success`
- **THEN** the user sees a success-styled toast with this text in the top-right corner

#### Scenario: Error message
- **WHEN** the server returns the next page with the flash message `error`
- **THEN** the user sees an error-styled toast with this text in the top-right corner

#### Scenario: Message is not repeated
- **WHEN** the user navigates to another page after a toast was shown
- **THEN** the same toast is not shown again

#### Scenario: Same message twice in a row
- **WHEN** the user stays on the same page and two consecutive responses carry a flash message with the same text, for example saving an edit form twice
- **THEN** the user sees a toast for each response

#### Scenario: Toast does not cover form actions
- **WHEN** a toast is shown after saving an edit form
- **THEN** the Save button at the bottom of the form is not covered by the toast

#### Scenario: Toast is closed manually
- **WHEN** the user selects the close button of a toast
- **THEN** the toast disappears before its duration ends

#### Scenario: Toast duration depends on status
- **WHEN** a success toast and an error toast are shown
- **THEN** the success toast disappears after about 4 seconds and the error toast after about 8 seconds
