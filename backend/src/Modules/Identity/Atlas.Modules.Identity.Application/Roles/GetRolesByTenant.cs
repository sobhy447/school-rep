using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Roles;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.Roles;

public sealed record GetRolesByTenantQuery : IQuery<IReadOnlyList<RoleResponse>>;

public sealed class GetRolesByTenantQueryHandler(ITenantContext tenantContext, IRoleRepository roles)
    : IQueryHandler<GetRolesByTenantQuery, IReadOnlyList<RoleResponse>>
{
    public async Task<Result<IReadOnlyList<RoleResponse>>> Handle(
        GetRolesByTenantQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<Role> result = await roles.ListForTenantAsync(tenantContext.TenantId, cancellationToken);

        IReadOnlyList<RoleResponse> response = result.Select(IdentityMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
