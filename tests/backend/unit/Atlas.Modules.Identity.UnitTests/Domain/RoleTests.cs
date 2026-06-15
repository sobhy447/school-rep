using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.ValueObjects;
using FluentAssertions;
using Xunit;

namespace Atlas.Modules.Identity.UnitTests.Domain;

public sealed class RoleTests
{
    private static LocalizedText Name() => LocalizedText.Create("مدير", "Manager");

    [Fact]
    public void Create_TenantRole_IsNotSystemRole()
    {
        Role role = Role.Create(tenantId: 5, "MANAGER", Name(), isSystemRole: false, createdBy: 1);

        role.TenantId.Should().Be(5);
        role.IsSystemRole.Should().BeFalse();
        role.IsActive.Should().BeTrue();
    }

    [Fact]
    public void Create_SystemRoleWithTenant_Throws()
    {
        Action act = () => Role.Create(tenantId: 5, "SUPER_ADMIN", Name(), isSystemRole: true, createdBy: 1);

        act.Should().Throw<ArgumentException>();
    }

    [Fact]
    public void Create_NonSystemRoleWithoutTenant_Throws()
    {
        Action act = () => Role.Create(tenantId: null, "MANAGER", Name(), isSystemRole: false, createdBy: 1);

        act.Should().Throw<ArgumentException>();
    }

    [Fact]
    public void Create_SystemRoleWithoutTenant_Succeeds()
    {
        Role role = Role.Create(tenantId: null, "SUPER_ADMIN", Name(), isSystemRole: true, createdBy: 1);

        role.TenantId.Should().BeNull();
        role.IsSystemRole.Should().BeTrue();
    }
}
