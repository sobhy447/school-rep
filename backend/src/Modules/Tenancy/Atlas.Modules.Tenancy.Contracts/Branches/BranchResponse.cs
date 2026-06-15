namespace Atlas.Modules.Tenancy.Contracts.Branches;

public sealed record BranchResponse(
    long BranchId,
    long TenantId,
    long CompanyId,
    string BranchCode,
    string NameAr,
    string NameEn,
    string? Address,
    string? Phone,
    bool IsMainBranch,
    bool IsActive,
    int EntityVersion);
