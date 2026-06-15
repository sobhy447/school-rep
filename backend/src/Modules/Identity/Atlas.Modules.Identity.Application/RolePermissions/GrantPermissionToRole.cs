using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.RolePermissions;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Identity.Application.RolePermissions;

public sealed record GrantPermissionToRoleCommand(
    long RoleId,
    long PermissionId,
    long PerformedBy) : ICommand<RolePermissionResponse>;

internal sealed class GrantPermissionToRoleCommandValidator : AbstractValidator<GrantPermissionToRoleCommand>
{
    public GrantPermissionToRoleCommandValidator()
    {
        RuleFor(command => command.RoleId).GreaterThan(0);
        RuleFor(command => command.PermissionId).GreaterThan(0);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class GrantPermissionToRoleCommandHandler(
    ITenantContext tenantContext,
    IRoleRepository roles,
    IPermissionRepository permissions,
    IRolePermissionRepository rolePermissions,
    IUnitOfWork unitOfWork) : ICommandHandler<GrantPermissionToRoleCommand, RolePermissionResponse>
{
    public async Task<Result<RolePermissionResponse>> Handle(
        GrantPermissionToRoleCommand command,
        CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        Role? role = await roles.GetByIdAsync(command.RoleId, cancellationToken);
        if (role is null || (role.TenantId is not null && role.TenantId != tenantId))
        {
            return Result.Failure<RolePermissionResponse>(IdentityErrors.RoleNotFound(command.RoleId));
        }

        Permission? permission = await permissions.GetByIdAsync(command.PermissionId, cancellationToken);
        if (permission is null)
        {
            return Result.Failure<RolePermissionResponse>(IdentityErrors.PermissionNotFound(command.PermissionId));
        }

        if (await rolePermissions.ExistsAsync(tenantId, command.RoleId, command.PermissionId, cancellationToken))
        {
            return Result.Failure<RolePermissionResponse>(IdentityErrors.PermissionAlreadyGranted());
        }

        RolePermission rolePermission = RolePermission.Create(
            tenantId,
            command.RoleId,
            command.PermissionId,
            command.PerformedBy);

        await rolePermissions.AddAsync(rolePermission, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return IdentityMapper.ToResponse(rolePermission);
    }
}
