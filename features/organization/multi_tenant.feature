Feature: Multi-tenant Organization Management
  In order to manage multiple organizations
  As an authenticated user
  I need to be able to create and manage organizations

  Background:
    Given I am signed in as "john@company.com"

  Scenario: Create a new organization
    When I create an organization with name "Acme Corporation" and slug "acme-corp"
    Then the organization should be created successfully
    And I should be the owner of "acme-corp"
    And the organization slug should be "acme-corp"

  Scenario: Invalid slug is rejected
    When I try to create an organization with name "Bad Org" and slug "Bad_Slug"
    Then the organization creation should fail
    And I should see an error about invalid slug format

  Scenario: Invite member to organization
    Given I own an organization "acme-corp"
    When I invite "john@company.com" to "acme-corp" with role "member"
    Then an invitation should be sent to "john@company.com"
    And the invitation status should be "pending"

  Scenario: Accept organization invitation
    Given I have received an invitation to join "acme-corp"
    When I accept the invitation
    Then I should become a member of "acme-corp"
    And my role should be "member"
    And the invitation status should be "accepted"

  Scenario: Member permissions
    Given I am a member of "acme-corp" with role "member"
    When I check if I can manage the organization
    Then I should not have management permissions

  Scenario: Admin permissions
    Given I am a member of "acme-corp" with role "admin"
    When I check if I can manage the organization
    Then I should have management permissions

  Scenario: Owner has all permissions
    Given I am the owner of "acme-corp"
    When I check if I can manage the organization
    Then I should have management permissions
    And I should be able to delete the organization
