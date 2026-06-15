namespace Atlas.Modules.Tenancy.Contracts.Branches;

public sealed record CreateBranchRequest(
    long CompanyId,
    string BranchCode,
    string NameAr,
    string NameEn,
    string? Address,
    string? Phone,
    bool IsMainBranch);
