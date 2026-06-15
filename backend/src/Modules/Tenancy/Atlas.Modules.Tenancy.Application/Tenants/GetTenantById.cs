using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Tenants;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Application.Tenants;

public sealed record GetTenantByIdQuery(long TenantId) : IQuery<TenantResponse>;

public sealed class GetTenantByIdQueryHandler(ITenantRepository tenants)
    : IQueryHandler<GetTenantByIdQuery, TenantResponse>
{
    public async Task<Result<TenantResponse>> Handle(GetTenantByIdQuery query, CancellationToken cancellationToken)
    {
        Tenant? tenant = await tenants.GetByIdAsync(query.TenantId, cancellationToken);

        return tenant is null
            ? Result.Failure<TenantResponse>(TenancyErrors.TenantNotFound(query.TenantId))
            : TenancyMapper.ToResponse(tenant);
    }
}
