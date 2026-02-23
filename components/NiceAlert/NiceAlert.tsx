import React from "react";
import { Modal, View, Text, Pressable, TextInput } from "react-native";

type Variant = "error" | "info" | "success";

type Props = {
  visible: boolean;
  title?: string;
  message: string;
  onClose: () => void;

  // opcional: aparência
  variant?: Variant;

  // opcional: input (token)
  showInput?: boolean;
  inputPlaceholder?: string;
  inputValue?: string;
  onChangeInput?: (text: string) => void;

  // opcional: botão confirmar
  confirmText?: string;
  onConfirm?: () => void;
  confirmDisabled?: boolean;
};

export default function NiceAlert({
  visible,
  title = "Ocorreu um erro",
  message,
  onClose,

  variant = "error",

  showInput = false,
  inputPlaceholder = "Digite aqui…",
  inputValue = "",
  onChangeInput,

  confirmText = "Confirmar",
  onConfirm,
  confirmDisabled = false,
}: Props) {
  const variantClasses: Record<Variant, string> = {
    error: "bg-red-100 dark:bg-red-900/40 border-red-300",
    info: "bg-sky-100 dark:bg-sky-900/40 border-sky-300",
    success: "bg-green-100 dark:bg-green-900/40 border-green-300",
  };

  return (
    <Modal animationType="fade" transparent visible={visible} onRequestClose={onClose}>
      <View className="flex-1 items-center justify-center bg-back/40 px-6">
        <View className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 p-6 shadow-2xl border border-neutral-200 dark:border-neutral-800">
          <View className={`mb-4 rounded-xl border px-3 py-2 ${variantClasses[variant]}`}>
            <Text className="text-lg font-semibold dark:text-white">{title}</Text>
          </View>

          <Text className="text-base text-neutral-700 dark:text-neutral-300">
            {message}
          </Text>

          {showInput && (
            <View className="mt-4">
              <TextInput
                value={inputValue}
                onChangeText={onChangeInput}
                autoCapitalize="none"
                autoCorrect={false}
                placeholder={inputPlaceholder}
                className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
              />
            </View>
          )}

          <View className="mt-6 flex-row justify-end gap-3">
            <Pressable
              accessibilityRole="button"
              onPress={onClose}
              className="h-11 rounded-xl bg-neutral-200 dark:bg-neutral-800 px-5 items-center justify-center"
            >
              <Text className="font-medium dark:text-white">Fechar</Text>
            </Pressable>

            {onConfirm && (
              <Pressable
                accessibilityRole="button"
                onPress={onConfirm}
                disabled={confirmDisabled}
                className={`h-11 rounded-xl px-5 items-center justify-center ${
                  confirmDisabled ? "bg-neutral-300 dark:bg-neutral-700" : "bg-green-600"
                }`}
              >
                <Text className="font-medium color-white">{confirmText}</Text>
              </Pressable>
            )}
          </View>
        </View>
      </View>
    </Modal>
  );
}