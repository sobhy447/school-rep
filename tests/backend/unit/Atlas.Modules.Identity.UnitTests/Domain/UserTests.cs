using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Domain.Users.Events;
using Atlas.Modules.Identity.Domain.ValueObjects;
using FluentAssertions;
using Xunit;

namespace Atlas.Modules.Identity.UnitTests.Domain;

public sealed class UserTests
{
    private const long TenantId = 7;

    private static User NewUser(Email? email = null) =>
        User.Create(
            TenantId,
            Username.Create("jdoe"),
            PasswordHash.Create("$2a$12$abcdefghijklmnopqrstuv"),
            email,
            phone: null,
            fullNameAr: null,
            fullNameEn: null,
            employeeId: null,
            forcePasswordChange: true,
            createdBy: 1);

    [Fact]
    public void Create_AssignsTenantAndDefaults()
    {
        User user = NewUser();

        user.TenantId.Should().Be(TenantId);
        user.IsActive.Should().BeTrue();
        user.MfaEnabled.Should().BeFalse();
        user.FailedLoginAttempts.Should().Be(0);
        user.EntityVersion.Should().Be(1);
        user.ForcePasswordChange.Should().BeTrue();
    }

    [Fact]
    public void Create_RaisesUserCreatedDomainEvent()
    {
        User user = NewUser();

        user.DomainEvents.Should().ContainSingle()
            .Which.Should().BeOfType<UserCreatedDomainEvent>()
            .Which.TenantId.Should().Be(TenantId);
    }

    [Fact]
    public void ChangePassword_ResetsLockoutAndAttempts()
    {
        User user = NewUser();
        var changedAt = new DateTime(2026, 1, 1, 0, 0, 0, DateTimeKind.Utc);

        user.ChangePassword(PasswordHash.Create("$2a$12$zzzzzzzzzzzzzzzzzzzzzz"), changedAt, forcePasswordChange: false, updatedBy: 2);

        user.PasswordChangedAtUtc.Should().Be(changedAt);
        user.ForcePasswordChange.Should().BeFalse();
        user.FailedLoginAttempts.Should().Be(0);
        user.LockoutUntilUtc.Should().BeNull();
    }

    [Fact]
    public void Email_WithInvalidFormat_Throws()
    {
        Action act = () => Email.Create("not-an-email");

        act.Should().Throw<ArgumentException>();
    }
}
