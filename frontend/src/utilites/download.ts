export const downloadBinary = (binary: Blob, fileName: string) => {
    const url = window?.URL.createObjectURL(binary);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window?.URL.revokeObjectURL(url);
}