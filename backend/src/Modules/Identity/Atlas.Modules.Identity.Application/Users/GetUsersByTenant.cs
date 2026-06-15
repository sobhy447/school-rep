using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Users;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.Users;

public sealed record GetUsersByTenantQuery : IQuery<IReadOnlyList<UserResponse>>;

public sealed class GetUsersByTenantQueryHandler(ITenantContext tenantContext, IUserRepository users)
    : IQueryHandler<GetUsersByTenantQuery, IReadOnlyList<UserResponse>>
{
    public async Task<Result<IReadOnlyList<UserResponse>>> Handle(
        GetUsersByTenantQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<User> result = await users.ListByTenantAsync(tenantContext.TenantId, cancellationToken);

        IReadOnlyList<UserResponse> response = result.Select(IdentityMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
