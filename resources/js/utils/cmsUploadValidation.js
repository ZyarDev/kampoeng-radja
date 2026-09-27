export const CMS_UPLOAD_LIMITS = {
    hero: { file: 100 * 1024 * 1024 },
    media: { file: 20 * 1024 * 1024 },
    promo: { file: 20 * 1024 * 1024 },
    product: { file: 20 * 1024 * 1024 },
    partner: { file: 5 * 1024 * 1024 },
    wahana: { file: 10 * 1024 * 1024, total: 30 * 1024 * 1024 },
    dining: { file: 10 * 1024 * 1024, total: 30 * 1024 * 1024 },
    gallery: { file: 10 * 1024 * 1024, total: 50 * 1024 * 1024 },
};

export function validateUploadFile(file, limit, label) {
    if (file && file.size > limit) return `Ukuran ${label} maksimal ${Math.round(limit / 1024 / 1024)} MB.`;
    return null;
}

export function validateUploadFiles(files, limits, label) {
    const invalid = files.find((file) => file.size > limits.file);
    if (invalid) return `Ukuran setiap ${label} maksimal ${Math.round(limits.file / 1024 / 1024)} MB.`;
    const total = files.reduce((sum, file) => sum + file.size, 0);
    if (limits.total && total > limits.total) return `Total ukuran ${label} maksimal ${Math.round(limits.total / 1024 / 1024)} MB.`;
    return null;
}
