using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.UserRoles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.UserRoles;

public sealed record GetUserRolesQuery(long UserId) : IQuery<IReadOnlyList<UserRoleResponse>>;

public sealed class GetUserRolesQueryHandler(ITenantContext tenantContext, IUserRoleRepository userRoles)
    : IQueryHandler<GetUserRolesQuery, IReadOnlyList<UserRoleResponse>>
{
    public async Task<Result<IReadOnlyList<UserRoleResponse>>> Handle(
        GetUserRolesQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<UserRole> result =
            await userRoles.ListByUserAsync(tenantContext.TenantId, query.UserId, cancellationToken);

        IReadOnlyList<UserRoleResponse> response = result.Select(IdentityMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
