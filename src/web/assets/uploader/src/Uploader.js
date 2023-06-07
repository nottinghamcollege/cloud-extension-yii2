/** global: Craft */
/** global: Garnish */

const getUploadUrl = function (data) {
  const formData = data.formData;
  const file = data.files[0];

  return Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
    data: Object.assign(formData, {
      file: {
        lastModified: file.lastModified,
        name: file.name,
        size: file.size,
        type: file.type,
      },
    }),
  }).then(() => data.submit());
};

Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.off('fileuploadadd');
    this.uploader = null;
    this.formData = {};
    this.$dropZone = settings.dropZone
    this.$fileInput = settings.fileInput;
    this.$fileInput.on('change', (event) => this.uploadFiles.call(this, event.target.files));
    this.resetCounters();

    if (this.allowedKinds && !this._extensionList) {
      this._createExtensionList();
    }

    this.$dropZone.on({
      dragover: (event) => {
        this.handleDragEvent(event);
        event.dataTransfer.dropEffect = 'copy';
      },
      drop: (event) => {
        this.handleDragEvent(event);
        this.uploadFiles(event.dataTransfer.files);
      },
      dragenter: this.handleDragEvent,
      dragleave: this.handleDragEvent,
    });
  },

  handleDragEvent: function(event) {
    if (!event?.dataTransfer?.files) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
  },

  /**
   * Set uploader parameters.
   */
  setParams: function (paramObject) {
    // If CSRF protection isn't enabled, these won't be defined.
    if (Craft?.csrfTokenName && Craft?.csrfTokenValue) {
      paramObject[Craft.csrfTokenName] = Craft.csrfTokenValue;
    }

    this.formData = paramObject;
  },
  uploadFiles: async function (FileList) {
    const files = [...FileList];
    const validFiles = files.filter((file) => {
      let valid = true;

      if (this._extensionList?.length) {
        const matches = file.name.match(/\.([a-z0-4_]+)$/i);
        const fileExtension = matches[1];

        if (this._extensionList.includes(fileExtension.toLowerCase())) {
          this._rejectedFiles.type.push('“' + file.name + '”');
          valid = false;
        }
      }

      if (file.size > this.settings.maxFileSize) {
        this._rejectedFiles.size.push('“' + file.name + '”');
        valid = false;
      }

      if (
        valid &&
        typeof this.settings.canAddMoreFiles === 'function' &&
        !this.settings.canAddMoreFiles(this._validFileCounter)
      ) {
        this._rejectedFiles.limit.push('“' + file.name + '”');
        valid = false;
      }

      if (valid) {
        this._totalBytes += file.size;
        this._validFileCounter++;
        this._inProgressCounter++;
      }

      return valid;
    });

    this.processErrorMessages();

    this.$element.trigger('fileuploadstart');

    for (const file of validFiles) {
      await this.uploadFile(file);
      this._inProgressCounter--;
    }

    this.resetCounters();
  },
  uploadFile: async function (file) {
    Object.assign(this.formData, {
      filename: file.name,
      lastModified: file.lastModified,
    });

    try {
      let response = await Craft.sendActionRequest(
        'POST',
        'cloud/get-upload-url',
        {
          data: this.formData,
        }
      );

      Object.assign(this.formData, response.data);

      await axios.put(response.data.url, file, {
        headers: {
          'Content-Type': file.type,
        },
        onUploadProgress: (axiosProgressEvent) => {
          this._uploadedBytes = this._uploadedBytes + axiosProgressEvent.loaded - this._lastUploadedBytes;
          this._lastUploadedBytes = axiosProgressEvent.loaded;
          this.$element.trigger('fileuploadprogressall', {
            loaded: this._uploadedBytes,
            total: this._totalBytes,
          });
        },
      });

      response = await axios.post(
        Craft.getActionUrl('cloud/create-asset'),
        this.formData
      );
      this.$element.trigger('fileuploaddone', response.data);
    } catch (error) {
      this.$element.trigger('fileuploadfail', {
        message: error.message,
        filename: file.name,
      });
    } finally {
      this._lastUploadedBytes = 0;
      this.$element.trigger('fileuploadalways');
    }
  },

  resetCounters: function () {
    this._totalBytes = 0;
    this._uploadedBytes = 0;
    this._lastUploadedBytes = 0;
    this._inProgressCounter = 0;
  },

  /**
   * Get the number of uploads in progress.
   */
  getInProgress: function () {
    return this._inProgressCounter;
  },
});

// Register it!
Craft.registerAssetUploaderClass('craft\\cloud\\fs\\AssetFs', Craft.CloudUploader);
