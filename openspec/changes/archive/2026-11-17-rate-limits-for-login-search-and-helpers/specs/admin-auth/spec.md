## ADDED Requirements

### Requirement: Login attempts are limited
The system SHALL limit failed login attempts in two ways: after 5 failed attempts for the same email address (compared case-insensitively) from the same IP address, further attempts for that pair SHALL be refused until the lockout ends; and after a configured number of failed attempts from one IP address across all email addresses within a configured window, further attempts from that IP address SHALL be refused until the window ends, whatever the email. A refusal SHALL show the same message next to the email field, without revealing whether the account exists or is deactivated. A successful login SHALL reset the counter of its email and IP pair and SHALL NOT reset the counter of the IP address.

#### Scenario: Same email and IP
- **WHEN** a guest fails 5 times to log in with the same email from one IP address and then submits the correct password
- **THEN** the login is refused with the throttle message

#### Scenario: Many emails from one IP
- **WHEN** a guest fails to log in with a different email each time from one IP address until the per-IP limit is reached and then submits correct credentials
- **THEN** the login is refused with the throttle message

#### Scenario: Another IP address
- **WHEN** the per-IP limit of one address is reached and a guest from another IP address logs in with correct credentials
- **THEN** the login succeeds

#### Scenario: Success does not reset the per-IP counter
- **WHEN** a guest from an IP address near the per-IP limit logs in successfully with a valid account and then fails again
- **THEN** the failures before the successful login still count toward the per-IP limit
