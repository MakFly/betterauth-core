Feature: API Authentication
  In order to access protected resources
  As an API consumer
  I need to be able to authenticate using access tokens

  Background:
    Given the API is in stateless mode
    And there is a user with email "user@example.com" and password "SecurePass123"

  Scenario: Successful sign in with valid credentials
    When I sign in with email "user@example.com" and password "SecurePass123"
    Then the sign in should be successful
    And I should receive an access token
    And I should receive a refresh token
    And the token type should be "Bearer"
    And the token should expire in 3600 seconds

  Scenario: Failed sign in with invalid email
    When I sign in with email "wrong@example.com" and password "SecurePass123"
    Then the sign in should fail
    And I should see an error "Invalid credentials"

  Scenario: Failed sign in with invalid password
    When I sign in with email "user@example.com" and password "WrongPassword"
    Then the sign in should fail
    And I should see an error "Invalid credentials"

  Scenario: Access token verification
    Given I have signed in with email "user@example.com" and password "SecurePass123"
    When I verify my access token
    Then the token should be valid
    And the token payload should contain my user ID
    And the token payload should contain my email

  Scenario: Refresh token to get new access token
    Given I have signed in with email "user@example.com" and password "SecurePass123"
    When I use my refresh token to get a new access token
    Then I should receive a new access token
    And I should receive a new refresh token
    And the old refresh token should be revoked

  Scenario: Cannot use expired access token
    Given I have an expired access token
    When I try to verify the expired token
    Then the token verification should fail
    And I should see an error "Invalid or expired token"

  Scenario: Revoke all tokens (logout from all devices)
    Given I have signed in with email "user@example.com" and password "SecurePass123"
    And I have signed in from another device
    When I revoke all my tokens
    Then all my refresh tokens should be revoked
    And I should not be able to use any old refresh token

  Scenario: Token contains correct payload structure
    Given I have signed in with email "user@example.com" and password "SecurePass123"
    When I decode my access token
    Then the token should have a "sub" field with my user ID
    And the token should have a "type" field with value "access"
    And the token should have an "iat" field with issue timestamp
    And the token should have an "exp" field with expiration timestamp
    And the token should have a "data" field with my email and name

  Scenario: PASETO token format validation
    Given I have signed in with email "user@example.com" and password "SecurePass123"
    Then my access token should start with "v4.local."
    And my access token should be encrypted with XChaCha20-Poly1305
