using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.UserRoles;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Identity.Application.UserRoles;

public sealed record AssignRoleToUserCommand(
    long UserId,
    long RoleId,
    long? CompanyId,
    DateTime? ExpiresAtUtc,
    long PerformedBy) : ICommand<UserRoleResponse>;

internal sealed class AssignRoleToUserCommandValidator : AbstractValidator<AssignRoleToUserCommand>
{
    public AssignRoleToUserCommandValidator()
    {
        RuleFor(command => command.UserId).GreaterThan(0);
        RuleFor(command => command.RoleId).GreaterThan(0);
        RuleFor(command => command.CompanyId).GreaterThan(0).When(command => command.CompanyId is not null);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class AssignRoleToUserCommandHandler(
    ITenantContext tenantContext,
    IUserRepository users,
    IRoleRepository roles,
    IUserRoleRepository userRoles,
    IUnitOfWork unitOfWork) : ICommandHandler<AssignRoleToUserCommand, UserRoleResponse>
{
    public async Task<Result<UserRoleResponse>> Handle(AssignRoleToUserCommand command, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        User? user = await users.GetByIdAsync(command.UserId, cancellationToken);
        if (user is null || user.TenantId != tenantId)
        {
            return Result.Failure<UserRoleResponse>(IdentityErrors.UserNotFound(command.UserId));
        }

        Role? role = await roles.GetByIdAsync(command.RoleId, cancellationToken);
        if (role is null || (role.TenantId is not null && role.TenantId != tenantId))
        {
            return Result.Failure<UserRoleResponse>(IdentityErrors.RoleNotFound(command.RoleId));
        }

        if (await userRoles.ExistsAsync(tenantId, command.UserId, command.RoleId, command.CompanyId, cancellationToken))
        {
            return Result.Failure<UserRoleResponse>(IdentityErrors.RoleAlreadyAssigned());
        }

        UserRole userRole = UserRole.Create(
            tenantId,
            command.UserId,
            command.RoleId,
            command.CompanyId,
            command.PerformedBy,
            command.ExpiresAtUtc);

        await userRoles.AddAsync(userRole, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return IdentityMapper.ToResponse(userRole);
    }
}
