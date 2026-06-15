using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.UserRoles;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentAssertions;
using NSubstitute;
using Xunit;

namespace Atlas.Modules.Identity.UnitTests.Application;

public sealed class AssignRoleToUserHandlerTests
{
    private const long TenantId = 7;

    private readonly ITenantContext _tenantContext = Substitute.For<ITenantContext>();
    private readonly IUserRepository _users = Substitute.For<IUserRepository>();
    private readonly IRoleRepository _roles = Substitute.For<IRoleRepository>();
    private readonly IUserRoleRepository _userRoles = Substitute.For<IUserRoleRepository>();
    private readonly IUnitOfWork _unitOfWork = Substitute.For<IUnitOfWork>();

    public AssignRoleToUserHandlerTests() => _tenantContext.TenantId.Returns(TenantId);

    private AssignRoleToUserCommandHandler Handler() => new(_tenantContext, _users, _roles, _userRoles, _unitOfWork);

    private static User TenantUser() =>
        User.Create(TenantId, Username.Create("jdoe"), PasswordHash.Create("$2a$12$aaaaaaaaaaaaaaaaaaaaaa"),
            null, null, null, null, null, false, 1);

    private static Role TenantRole() =>
        Role.Create(TenantId, "MANAGER", LocalizedText.Create("مدير", "Manager"), false, 1);

    [Fact]
    public async Task Handle_WhenAlreadyAssigned_ReturnsConflict()
    {
        _users.GetByIdAsync(10, Arg.Any<CancellationToken>()).Returns(TenantUser());
        _roles.GetByIdAsync(20, Arg.Any<CancellationToken>()).Returns(TenantRole());
        _userRoles.ExistsAsync(TenantId, 10, 20, null, Arg.Any<CancellationToken>()).Returns(true);

        var command = new AssignRoleToUserCommand(10, 20, null, null, PerformedBy: 1);
        Result<Contracts.UserRoles.UserRoleResponse> result = await Handler().Handle(command, CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Code.Should().Be("Identity.UserRole.AlreadyAssigned");
    }

    [Fact]
    public async Task Handle_WhenValid_CreatesAssignment()
    {
        _users.GetByIdAsync(10, Arg.Any<CancellationToken>()).Returns(TenantUser());
        _roles.GetByIdAsync(20, Arg.Any<CancellationToken>()).Returns(TenantRole());
        _userRoles.ExistsAsync(TenantId, 10, 20, null, Arg.Any<CancellationToken>()).Returns(false);

        var command = new AssignRoleToUserCommand(10, 20, null, null, PerformedBy: 1);
        Result<Contracts.UserRoles.UserRoleResponse> result = await Handler().Handle(command, CancellationToken.None);

        result.IsSuccess.Should().BeTrue();
        result.Value.TenantId.Should().Be(TenantId);
        await _userRoles.Received(1).AddAsync(Arg.Is<UserRole>(ur => ur.TenantId == TenantId && ur.UserId == 10 && ur.RoleId == 20), Arg.Any<CancellationToken>());
        await _unitOfWork.Received(1).SaveChangesAsync(Arg.Any<CancellationToken>());
    }
}
