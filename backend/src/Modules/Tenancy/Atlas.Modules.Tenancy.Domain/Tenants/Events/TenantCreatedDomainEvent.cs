using Atlas.SharedKernel.Events;

namespace Atlas.Modules.Tenancy.Domain.Tenants.Events;

public sealed record TenantCreatedDomainEvent(string TenantCode) : DomainEvent;
