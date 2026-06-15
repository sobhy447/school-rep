using Atlas.SharedKernel.Events;

namespace Atlas.Modules.Tenancy.Domain.Companies.Events;

public sealed record CompanyCreatedDomainEvent(long TenantId, string CompanyCode) : DomainEvent;
