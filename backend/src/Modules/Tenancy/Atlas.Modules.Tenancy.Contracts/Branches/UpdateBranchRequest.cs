namespace Atlas.Modules.Tenancy.Contracts.Branches;

public sealed record UpdateBranchRequest(
    string NameAr,
    string NameEn,
    string? Address,
    string? Phone,
    bool IsMainBranch);
