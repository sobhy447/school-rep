using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.RolePermissions;
using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.RolePermissions;

public sealed record GetRolePermissionsQuery(long RoleId) : IQuery<IReadOnlyList<RolePermissionResponse>>;

public sealed class GetRolePermissionsQueryHandler(ITenantContext tenantContext, IRolePermissionRepository rolePermissions)
    : IQueryHandler<GetRolePermissionsQuery, IReadOnlyList<RolePermissionResponse>>
{
    public async Task<Result<IReadOnlyList<RolePermissionResponse>>> Handle(
        GetRolePermissionsQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<RolePermission> result =
            await rolePermissions.ListByRoleAsync(tenantContext.TenantId, query.RoleId, cancellationToken);

        IReadOnlyList<RolePermissionResponse> response = result.Select(IdentityMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
